<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SalesFieldMapping;
use App\Models\SalesUpload;
use App\Models\SalesData;
use App\Models\SalesReport;
use App\Models\PortfolioCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesTemplateExport;
use App\Imports\SalesDataImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SalesAnalysisController extends Controller
{
    private function authorizeSalesCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'sales_analysis');
    }

    public const FIELDS = [
        'date'                        => 'Date',
        'branch'                      => 'Branch',
        'document_number'             => 'Document Number',
        'business_unit'               => 'Business Unit',
        'business_sector'             => 'Business Sector',
        'sales_channel'               => 'Sales Channel',
        'country'                     => 'Country',
        'document_type'               => 'Document Type',
        'sales_person'                => 'Sales Person',
        'service_provider_name'       => 'Service Provider Name',
        'service_provider_type'       => 'Service Provider Type',
        'service_provider_birth_year' => 'Service Provider Birth Year',
        'principle'                   => 'Principle',
        'product_category'            => 'Product Category',
        'product_sub_category'        => 'Product Sub Category',
        'product_item'                => 'Product Item',
        'measurement_unit'            => 'Measurement Unit',
        'price_per_unit'              => 'Price Per Unit',
        'customer_name'               => 'Customer Name',
        'zone'                        => 'Zone',
        'quantity'                    => 'Quantity',
        'sales_value'                 => 'Sales Value',
        'cash_discount'               => 'Cash Discount',
        'quantity_discount'           => 'Quantity Discount',
        'special_discount'            => 'Special Discount',
        'other_discounts'             => 'Other Discounts',
        'net_sales_value'             => 'Net Sales Value',
    ];

    private const METRIC_FIELDS = [
        'quantity', 'sales_value', 'cash_discount',
        'quantity_discount', 'special_discount',
        'other_discounts', 'net_sales_value', 'price_per_unit',
        'service_provider_birth_year',
    ];

    private const NON_DIMENSION_FIELDS = [
        'quantity', 'sales_value', 'cash_discount',
        'quantity_discount', 'special_discount',
        'other_discounts', 'net_sales_value', 'price_per_unit',
        'service_provider_birth_year', 'document_number', 'date',
    ];

    // ── Helpers ────────────────────────────────────────────────

    private function getActiveFields($companyId): array
    {
        $active = SalesFieldMapping::where('portfolio_company_id', $companyId)
            ->where('is_active', true)->orderBy('sort_order')
            ->pluck('field_key')->toArray();
        return empty($active) ? array_keys(self::FIELDS) : $active;
    }

    private function getActiveDimensionFields($companyId): array
    {
        $active = $this->getActiveFields($companyId);
        return array_filter($active, fn($k) => !in_array($k, self::NON_DIMENSION_FIELDS));
    }

    private function getActiveMetricFields($companyId): array
    {
        $active = $this->getActiveFields($companyId);
        return array_filter($active, fn($k) => in_array($k, self::METRIC_FIELDS));
    }

    // ── Period Expressions ─────────────────────────────────────
    // Returns [label_expr, sort_expr]
    // label → what user sees:  2024-Jan, 2024-Q1, 2024-H1, 2024
    // sort  → numeric value for correct chronological ORDER BY
    private function getPeriodExpressions(string $period): array
    {
        return match($period) {
            'monthly' => [
                "DATE_FORMAT(`date`, '%Y-%b')",
                "DATE_FORMAT(`date`, '%Y%m') + 0",
            ],
            'quarterly' => [
                "CONCAT(YEAR(`date`), '-Q', QUARTER(`date`))",
                "YEAR(`date`) * 10 + QUARTER(`date`)",
            ],
            'semi_annually' => [
                "CONCAT(YEAR(`date`), '-H', IF(MONTH(`date`) <= 6, 1, 2))",
                "YEAR(`date`) * 10 + IF(MONTH(`date`) <= 6, 1, 2)",
            ],
            'annually' => [
                "DATE_FORMAT(`date`, '%Y')",
                "YEAR(`date`)",
            ],
            default => [
                "DATE_FORMAT(`date`, '%Y-%b')",
                "DATE_FORMAT(`date`, '%Y%m') + 0",
            ],
        };
    }

    // ── Field Mapping ──────────────────────────────────────────

    public function fieldMapping($companyId)
    {
        $company = $this->authorizeCompany((int) $companyId, 'sales_analysis');
        $saved   = SalesFieldMapping::where('portfolio_company_id', $companyId)
            ->pluck('is_active', 'field_key')->toArray();

        $fields = collect(self::FIELDS)->map(function ($label, $key) use ($saved) {
            return [
                'key'       => $key,
                'label'     => $label,
                'is_active' => isset($saved[$key]) ? (bool) $saved[$key] : true,
            ];
        })->values();

        return Inertia::render('Sales/FieldMapping', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'fields'  => $fields,
        ]);
    }

    public function saveFieldMapping(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'fields'          => ['required', 'array'],
            'fields.*.key'    => ['required', 'string'],
            'fields.*.active' => ['required', 'boolean'],
        ]);

        foreach ($request->fields as $item) {
            SalesFieldMapping::updateOrCreate(
                ['portfolio_company_id' => $companyId, 'field_key' => $item['key']],
                ['is_active' => $item['active'], 'sort_order' => array_search($item['key'], array_keys(self::FIELDS))]
            );
        }

        return back()->with('flash', ['success' => 'Field mapping saved successfully.']);
    }

    // ── Download Template ──────────────────────────────────────

    public function downloadTemplate($companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $activeFields = $this->getActiveFields($companyId);
        $headers      = array_map(fn($key) => self::FIELDS[$key] ?? $key, $activeFields);
        return Excel::download(new SalesTemplateExport($headers), 'sales_template.xlsx');
    }

    // ── Upload Page ────────────────────────────────────────────

    public function uploadPage($companyId)
    {
        $company = $this->authorizeSalesCompany($companyId);
        $uploads = SalesUpload::where('portfolio_company_id', $companyId)
            ->with('uploadedBy')->orderByDesc('created_at')->take(10)->get()
            ->map(fn($u) => [
                'id'          => $u->id,
                'period_from' => $u->period_from?->format('Y-m-d'),
                'period_to'   => $u->period_to?->format('Y-m-d'),
                'date_format' => $u->date_format,
                'row_count'   => $u->row_count,
                'status'      => $u->status,
                'uploaded_by' => $u->uploadedBy?->name,
                'created_at'  => $u->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Sales/Upload', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'uploads' => $uploads,
        ]);
    }

    // ── Process Upload ─────────────────────────────────────────

    public function processUpload(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'file'        => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
            'date_format' => ['required', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY/MM/DD,DD-MM-YYYY,MM-DD-YYYY,YYYY-MM-DD'],
        ]);

        $path   = $request->file('file')->store('sales-uploads', 'local');
        $upload = SalesUpload::create([
            'portfolio_company_id' => $companyId,
            'file_path'            => $path,
            'original_filename'    => $request->file('file')->getClientOriginalName(),
            'period_from'          => $request->period_from,
            'period_to'            => $request->period_to,
            'date_format'          => $request->date_format,
            'status'               => 'processing',
            'uploaded_by'          => Auth::id(),
        ]);

         
        dispatch(new \App\Jobs\ProcessSalesUpload($upload->id));

        return back()->with('flash', [
            'success' => 'Processing in background — refresh in a moment to see status.'
        ]);
    }

     public function deleteUpload(PortfolioCompany $company, $upload)
    {
        $this->authorizeCompany($company, 'sales_analysis');
        $uploadRecord = \App\Models\SalesUpload::where('portfolio_company_id', $company->id)->findOrFail($upload);
        \App\Models\SalesData::where('portfolio_company_id', $company->id)->where('upload_id', $upload)->delete();
        $uploadRecord->delete();
        return response()->json(['success' => true]);
    }

    // ── Reports Page ───────────────────────────────────────────

    public function reportsPage($companyId)
    {
        $company      = $this->authorizeSalesCompany($companyId);
        $hasData      = SalesData::where('portfolio_company_id', $companyId)->exists();

        $activeKeys      = $this->getActiveFields($companyId);
        $activeFields    = array_intersect_key(self::FIELDS, array_flip($activeKeys));

        $dimensionKeys   = $this->getActiveDimensionFields($companyId);
        $dimensionFields = array_intersect_key(self::FIELDS, array_flip($dimensionKeys));

        $metricKeys      = $this->getActiveMetricFields($companyId);
        $metricFields    = array_intersect_key(self::FIELDS, array_flip($metricKeys));
        if (empty($metricFields)) {
            $metricFields = ['net_sales_value' => 'Net Sales Value'];
        }

        return Inertia::render('Sales/Reports', [
            'company'         => ['id' => $company->id, 'name' => $company->name],
            'hasData'         => $hasData,
            'fields'          => $activeFields,
            'dimensionFields' => $dimensionFields,
            'metricFields'    => $metricFields,
            'reportTypes'     => $this->getReportTypes(),
        ]);
    }

    // ── Run Report ─────────────────────────────────────────────

    public function runReport(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'report_type' => ['required', 'string'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
            'period'      => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'  => ['nullable', 'string'],
            'dimension2'  => ['nullable', 'string'],
            'metric'      => ['nullable', 'string'],
            'top_n'       => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
            'customer_nature'   => $this->runCustomerNature($companyId, $request),
            'period_comparison' => $this->runPeriodComparison($companyId, $request),
            'trend'             => $this->runTrend($query, $request),
            'two_factors_trend' => $this->runTwoFactorsTrend($query, $request),
            default             => ['error' => 'Unknown report type'],
         } ;

        return response()->json($result);
    }

    // ── Export Report to Excel ─────────────────────────────────

    public function exportReport(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'report_type' => ['required', 'string'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
            'period'      => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'  => ['nullable', 'string'],
            'dimension2'  => ['nullable', 'string'],
            'metric'      => ['nullable', 'string'],
            'top_n'       => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        try {
        $company = PortfolioCompany::findOrFail($companyId);

        $query = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
            'customer_nature'   => $this->runCustomerNature($companyId, $request),
            'period_comparison' => $this->runPeriodComparison($companyId, $request),
            'trend'             => $this->runTrend($query, $request),
            'two_factors_trend' => $this->runTwoFactorsTrend($query, $request),
            default             => [],
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ── Style helpers ──
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2d5a9e']]],
        ];
        $totalStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0f2040']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2d5a9e']]],
        ];
        $altStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8fafc']],
        ];
        $numberFormat = '#,##0.00';

        $metricLabel = self::FIELDS[$request->metric ?? 'net_sales_value'] ?? ($request->metric ?? 'Net Sales Value');
        $reportTypes = $this->getReportTypes();
        $reportLabel = collect($reportTypes)->firstWhere('key', $request->report_type)['label'] ?? $request->report_type;

        // ── Title row ──
        $sheet->setCellValue('A1', $company->name . ' — ' . $reportLabel);
        $sheet->setCellValue('A2', 'Period: ' . $request->date_from . ' to ' . $request->date_to);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1e3a5f');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('6b7280');
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');

        $row = 4;

        // ── Build rows based on report type ──
        if ($result['type'] === 'single_dimension') {
            $dimLabel = self::FIELDS[$result['dimension']] ?? $result['dimension'];
            $sheet->fromArray([$dimLabel, $metricLabel, 'Transactions', '% Share'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            $rows = collect($result['rows']);
            $total = $rows->sum(fn($r) => floatval($r['value'] ?? 0));
            foreach ($rows as $i => $r) {
                $share = $total > 0 ? round(floatval($r['value']) / $total * 100, 2) : 0;
                $sheet->fromArray([$r['label'], floatval($r['value']), intval($r['transactions']), $share . '%'], null, "A$row");
                $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:D$row")->applyFromArray($altStyle);
                $row++;
            }
            $sheet->fromArray(['Total', $total, $rows->sum(fn($r) => intval($r['transactions'])), '100%'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($totalStyle);
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numberFormat);
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'matrix') {
            $dim1Label = self::FIELDS[$result['dim1']] ?? $result['dim1'];
            $columns   = $result['columns'];
            $headers   = array_merge([$dim1Label], $columns, ['Total']);
            $sheet->fromArray($headers, null, "A$row");
            $sheet->getStyle("A$row:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)->applyFromArray($headerStyle);
            $row++;
            foreach ($result['rows'] as $i => $r) {
                $rowData = [$r['label']];
                $rowTotal = 0;
                foreach ($columns as $col) { $v = $r[$col] ?? 0; $rowData[] = $v; $rowTotal += $v; }
                $rowData[] = $rowTotal;
                $sheet->fromArray($rowData, null, "A$row");
                if ($i % 2 === 1) $sheet->getStyle("A$row:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)->applyFromArray($altStyle);
                $row++;
            }
            foreach (range(1, count($headers)) as $ci) {
                $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
            }

        } elseif ($result['type'] === 'trend') {
            $sheet->fromArray(['Period', $metricLabel, 'vs Previous %'], null, "A$row");
            $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
            $row++;
            $rows = $result['rows'];
            $total = 0;
            foreach ($rows as $i => $r) {
                $prev = $i > 0 ? floatval($rows[$i-1]['value']) : null;
                $gr   = ($prev && $prev > 0) ? round((floatval($r['value']) - $prev) / $prev * 100, 2) : null;
                $sheet->fromArray([$r['period'], floatval($r['value']), $gr !== null ? $gr . '%' : '—'], null, "A$row");
                $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:C$row")->applyFromArray($altStyle);
                $total += floatval($r['value']);
                $row++;
            }
            $sheet->fromArray(['Total', $total, ''], null, "A$row");
            $sheet->getStyle("A$row:C$row")->applyFromArray($totalStyle);
            foreach (['A','B','C'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'period_comparison') {
            $p1 = $result['period1']['from'] . ' → ' . $result['period1']['to'];
            $p2 = $result['period2']['from'] . ' → ' . $result['period2']['to'];
            $sheet->fromArray(['Label', 'Period 1 (' . $p1 . ')', 'Period 2 (' . $p2 . ')', 'Change %'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            foreach (collect($result['rows']) as $i => $r) {
                $sheet->fromArray([$r['label'], floatval($r['period1']), floatval($r['period2']), $r['change'] !== null ? $r['change'] . '%' : 'N/A'], null, "A$row");
                $sheet->getStyle("B$row:C$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:D$row")->applyFromArray($altStyle);
                $row++;
            }
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'customer_nature') {
            $sheet->fromArray(['Customer Category', 'Count', 'Total Sales'], null, "A$row");
            $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
            $row++;
            $catLabels = [
                'new' => 'New Customers', 'repeating' => 'Repeating',
                'active' => 'Active (3+ yrs)', 'stop' => 'Stop',
                'dead' => 'Dead', 'stop_reactivated' => 'Stop Reactivated',
            ];
            foreach ($result['categories'] as $cat) {
                $sheet->fromArray([$catLabels[$cat['label']] ?? $cat['label'], $cat['count'], floatval($cat['total_sales'])], null, "A$row");
                $sheet->getStyle("C$row")->getNumberFormat()->setFormatCode($numberFormat);
                $row++;
            }
            // Detail sheets per category
            foreach ($result['categories'] as $cat) {
                if (empty($cat['customers'])) continue;
                $catLabel = $catLabels[$cat['label']] ?? $cat['label'];
                $detailSheet = $spreadsheet->createSheet();
                $detailSheet->setTitle(substr($catLabel, 0, 31));
                $detailSheet->fromArray(['Customer Name', 'Sales', '% of Total'], null, 'A1');
                $detailSheet->getStyle('A1:C1')->applyFromArray($headerStyle);
                foreach ($cat['customers'] as $i => $c) {
                    $detailSheet->fromArray([$c['name'], floatval($c['sales']), $c['percentage'] . '%'], null, 'A' . ($i + 2));
                    $detailSheet->getStyle('B' . ($i + 2))->getNumberFormat()->setFormatCode($numberFormat);
                }
                foreach (['A','B','C'] as $col) $detailSheet->getColumnDimension($col)->setAutoSize(true);
            }
            $spreadsheet->setActiveSheetIndex(0);
            foreach (['A','B','C'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'two_factors_trend') {
            $dim1Label = self::FIELDS[$result['dim1']] ?? $result['dim1'];
            $dim2Label = self::FIELDS[$result['dim2']] ?? $result['dim2'];
            $periods   = $result['periods'];
            $headers   = array_merge(["$dim1Label / $dim2Label"], $periods->toArray(), ['Total']);
            $sheet->fromArray($headers, null, "A$row");
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($headerStyle);
            $row++;
            foreach ($result['rows'] as $parent) {
                // Parent row
                $parentData = [$parent['label']];
                foreach ($periods as $p) $parentData[] = $parent['cells'][$p]['value'] ?? 0;
                $parentData[] = $parent['total'];
                $sheet->fromArray($parentData, null, "A$row");
                $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
                ]);
                $row++;
                // Child rows
                foreach ($parent['children'] as $child) {
                    $childData = ['  ' . $child['label']];
                    foreach ($periods as $p) $childData[] = $child['cells'][$p]['value'] ?? 0;
                    $childData[] = $child['total'];
                    $sheet->fromArray($childData, null, "A$row");
                    $row++;
                }
            }
            for ($ci = 1; $ci <= count($headers); $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
            }

        } elseif ($result['type'] === 'ranking') {
            // Flatten ranking: branch, rank, products, total
            $sheet->fromArray(['Branch', 'Rank', 'Product/Item', 'Sales Value'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            foreach ($result['rows'] as $branchRow) {
                for ($r = 1; $r <= $result['num_ranks']; $r++) {
                    $rankData = $branchRow['ranks'][$r] ?? ['count' => 0, 'products' => []];
                    foreach ($rankData['products'] as $prod) {
                        $sheet->fromArray([$branchRow['branch'], 'Rank ' . $r, $prod['product'], floatval($prod['value'])], null, "A$row");
                        $sheet->getStyle("D$row")->getNumberFormat()->setFormatCode($numberFormat);
                        $row++;
                    }
                }
            }
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Sheet title ──
        $sheet->setTitle(substr($reportLabel, 0, 31));

        // ── Stream to browser ──
        $writer   = new Xlsx($spreadsheet);
        $filename = $company->name . '_' . $reportLabel . '_' . $request->date_from . '_' . $request->date_to . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0',
        ]);
        } catch (\Throwable $e) {
            \Log::error('Sales export failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Export failed. Please try again or contact support.'], 500);
        }
    }

    // ── Single Dimension ───────────────────────────────────────

    private function runSingleDimension($query, $request)
    {
        $dimension = $request->dimension1 ?? 'branch';
        $metric    = $request->metric ?? 'net_sales_value';
        $topN      = $request->top_n ?? 50;

        $rows = $query->whereNotNull($dimension)->where($dimension, '!=', '')
            ->selectRaw("`$dimension` as label, SUM(`$metric`) as value, COUNT(*) as transactions")
            ->groupBy($dimension)->orderByDesc('value')->limit($topN)->get();

        return ['type' => 'single_dimension', 'dimension' => $dimension, 'metric' => $metric, 'rows' => $rows];
    }

    // ── Matrix ─────────────────────────────────────────────────

    private function runMatrix($query, $request)
    {
        $dim1   = $request->dimension1 ?? 'zone';
        $dim2   = $request->dimension2 ?? 'product_category';
        $metric = $request->metric ?? 'net_sales_value';

        $rows = $query->whereNotNull($dim1)->whereNotNull($dim2)
            ->where($dim1, '!=', '')->where($dim2, '!=', '')
            ->selectRaw("`$dim1` as dim1, `$dim2` as dim2, SUM(`$metric`) as value")
            ->groupBy($dim1, $dim2)->orderBy($dim1)->orderBy($dim2)->get();

        $dim1Values = $rows->pluck('dim1')->unique()->values();
        $dim2Values = $rows->pluck('dim2')->unique()->sort()->values();

        $matrix = [];
        foreach ($dim1Values as $d1) {
            $row = ['label' => $d1];
            foreach ($dim2Values as $d2) {
                $found    = $rows->first(fn($r) => $r->dim1 === $d1 && $r->dim2 === $d2);
                $row[$d2] = $found ? (float) $found->value : 0;
            }
            $matrix[] = $row;
        }

        return ['type' => 'matrix', 'dim1' => $dim1, 'dim2' => $dim2, 'metric' => $metric, 'columns' => $dim2Values, 'rows' => $matrix];
    }

    // ── Branch Product Ranking ─────────────────────────────────
    private function runRanking($query, $request)
    {
        $metric = $request->metric ?? 'net_sales_value';
        $dim = $request->dimension1 ?? 'product_category';
        if ($dim === 'branch') {
            $dim = 'product_category';
        }

        $data = $query->whereNotNull('branch')->whereNotNull($dim)
            ->where('branch', '!=', '')->where($dim, '!=', '')
            ->selectRaw("`branch`, `$dim` as product_dim, SUM(`$metric`) as value")
            ->groupBy('branch', $dim)->get();

        $branches    = $data->pluck('branch')->unique()->sort()->values();
        $products    = $data->pluck('product_dim')->unique()->values();
        $numBranches = $branches->count();

        $rankMap = [];
        foreach ($products as $product) {
            $productRows = $data->where('product_dim', $product)->sortByDesc('value')->values();
            foreach ($productRows as $i => $row) {
                $rank   = $i + 1;
                $branch = $row->branch;
                if (!isset($rankMap[$branch][$rank])) {
                    $rankMap[$branch][$rank] = [];
                }
                $rankMap[$branch][$rank][] = [
                    'product' => $product,
                    'value'   => (float) $row->value,
                ];
            }
        }

        $rows = [];
        foreach ($branches as $branch) {
            $rankCells = [];
            for ($r = 1; $r <= $numBranches; $r++) {
                $items         = $rankMap[$branch][$r] ?? [];
                $rankCells[$r] = [
                    'count'    => count($items),
                    'products' => $items,
                    'total'    => array_sum(array_column($items, 'value')),
                ];
            }
            $rows[] = ['branch' => $branch, 'ranks' => $rankCells];
        }

        return [
            'type'      => 'ranking',
            'metric'    => $metric,
            'dimension' => $dim,
            'num_ranks' => $numBranches,
            'branches'  => $branches->values(),
            'rows'      => $rows,
        ];
    }

    // ── Customer Nature ────────────────────────────────────────

    private function runCustomerNature($companyId, $request)
    {
        $currentYear = date('Y', strtotime($request->date_to));
        $lastYear    = $currentYear - 1;
        $twoYearsAgo = $currentYear - 2;
        $metric      = $request->metric ?? 'net_sales_value';

        $thisYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $prevYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $lastYear)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $twoYears = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $twoYearsAgo)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $active = SalesData::where('portfolio_company_id', $companyId)
            ->whereNotNull('customer_name')
            ->selectRaw('customer_name, COUNT(DISTINCT YEAR(`date`)) as year_count')
            ->groupBy('customer_name')->having('year_count', '>=', 3)
            ->pluck('customer_name')->unique();

        $buckets = [
            'new'              => $thisYear->diff($prevYear)->diff($twoYears)->values(),
            'repeating'        => $thisYear->intersect($prevYear)->diff($twoYears)->values(),
            'active'           => $thisYear->intersect($active)->values(),
            'stop'             => $prevYear->diff($thisYear)->values(),
            'dead'             => $twoYears->diff($prevYear)->diff($thisYear)->values(),
            'stop_reactivated' => $thisYear->intersect($twoYears)->diff($prevYear)->values(),
        ];

        $salesByCustomer = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->selectRaw("customer_name, SUM(`$metric`) as total_sales")
            ->groupBy('customer_name')
            ->get()
            ->keyBy('customer_name');

        $grandTotal = $salesByCustomer->sum('total_sales');

        $categories = collect($buckets)->map(function ($customers, $key) use ($salesByCustomer, $grandTotal) {
            $rows = $customers->map(function ($name) use ($salesByCustomer, $grandTotal) {
                $sales = (float) ($salesByCustomer[$name]->total_sales ?? 0);
                return [
                    'name'       => $name,
                    'sales'      => $sales,
                    'percentage' => $grandTotal > 0 ? round($sales / $grandTotal * 100, 2) : 0,
                ];
            })->sortByDesc('sales')->values();

            return [
                'label'       => $key,
                'count'       => $customers->count(),
                'total_sales' => $rows->sum('sales'),
                'customers'   => $rows,
            ];
        });

        return [
            'type'        => 'customer_nature',
            'year'        => $currentYear,
            'grand_total' => (float) $grandTotal,
            'metric'      => $metric,
            'categories'  => $categories,
        ];
    }

    // ── Period Comparison ──────────────────────────────────────

    private function runPeriodComparison($companyId, $request)
    {
        $metric = $request->metric ?? 'net_sales_value';
        $dim    = $request->dimension1 ?? 'branch';

        $period1 = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull($dim)->where($dim, '!=', '')
            ->selectRaw("`$dim` as label, SUM(`$metric`) as value")
            ->groupBy($dim)->get()->keyBy('label');

        $period2 = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->compare_from, $request->compare_to])
            ->whereNotNull($dim)->where($dim, '!=', '')
            ->selectRaw("`$dim` as label, SUM(`$metric`) as value")
            ->groupBy($dim)->get()->keyBy('label');

        $allLabels = $period1->keys()->merge($period2->keys())->unique()->values();

        $rows = $allLabels->map(function ($label) use ($period1, $period2) {
            $v1 = (float) ($period1[$label]->value ?? 0);
            $v2 = (float) ($period2[$label]->value ?? 0);
            return [
                'label'   => $label,
                'period1' => $v1,
                'period2' => $v2,
                'change'  => $v1 > 0 ? round(($v2 - $v1) / $v1 * 100, 2) : null,
            ];
        });

        return [
            'type'    => 'period_comparison',
            'period1' => ['from' => $request->date_from,    'to' => $request->date_to],
            'period2' => ['from' => $request->compare_from, 'to' => $request->compare_to],
            'metric'  => $metric,
            'rows'    => $rows,
        ];
    }

    // ── Trend Over Time ────────────────────────────────────────

    private function runTrend($query, $request)
    {
        $metric = $request->metric ?? 'net_sales_value';
        $period = $request->period ?? 'monthly';
        $rows   = $this->buildTrendRows($query, $metric, $period);

        return ['type' => 'trend', 'metric' => $metric, 'period' => $period, 'rows' => $rows];
    }

    // ── Two Factors Trend ──────────────────────────────────────

    private function runTwoFactorsTrend($query, $request)
    {
        $dim1   = $request->dimension1 ?? 'branch';
        $dim2   = $request->dimension2 ?? 'product_category';
        $metric = $request->metric ?? 'net_sales_value';
        $period = $request->period ?? 'monthly';

        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $data = $query
            ->whereNotNull($dim1)->where($dim1, '!=', '')
            ->whereNotNull($dim2)->where($dim2, '!=', '')
            ->selectRaw("
                `$dim1`     as dim1,
                `$dim2`     as dim2,
                $labelExpr  as period_label,
                $sortExpr   as sort_key,
                SUM(`$metric`) as value
            ")
            ->groupBy('dim1', 'dim2', 'period_label', 'sort_key')
            ->orderBy('dim1')
            ->orderBy('dim2')
            ->orderBy('sort_key')
            ->get();

        $periods = $data
            ->sortBy('sort_key')
            ->pluck('period_label')
            ->unique()
            ->values();

        $grouped = [];
        foreach ($data as $row) {
            $grouped[$row->dim1][$row->dim2][$row->period_label] = (float) $row->value;
        }

        $resultRows = [];
        foreach ($grouped as $d1 => $dim2Groups) {

            $parentTotals = [];
            foreach ($periods as $p) {
                $parentTotals[$p] = collect($dim2Groups)->sum(fn($d2Data) => $d2Data[$p] ?? 0);
            }

            $subRows = [];
            foreach ($dim2Groups as $d2 => $periodData) {
                $cells     = [];
                $prevValue = null;
                foreach ($periods as $p) {
                    $val        = $periodData[$p] ?? 0;
                    $gr         = ($prevValue !== null && $prevValue > 0)
                        ? round(($val - $prevValue) / $prevValue * 100, 1) : 0;
                    $cells[$p]  = ['value' => $val, 'gr' => $gr];
                    $prevValue  = $val;
                }
                $subRows[] = [
                    'label' => $d2,
                    'cells' => $cells,
                    'total' => array_sum(array_column($cells, 'value')),
                ];
            }

            $parentCells = [];
            $prevParent  = null;
            foreach ($periods as $p) {
                $val              = $parentTotals[$p];
                $gr               = ($prevParent !== null && $prevParent > 0)
                    ? round(($val - $prevParent) / $prevParent * 100, 1) : 0;
                $parentCells[$p]  = ['value' => $val, 'gr' => $gr];
                $prevParent       = $val;
            }

            $resultRows[] = [
                'label'    => $d1,
                'cells'    => $parentCells,
                'total'    => array_sum(array_column($parentCells, 'value')),
                'children' => $subRows,
            ];
        }

        return [
            'type'    => 'two_factors_trend',
            'dim1'    => $dim1,
            'dim2'    => $dim2,
            'metric'  => $metric,
            'period'  => $period,
            'periods' => $periods,
            'rows'    => $resultRows,
        ];
    }

    // ── Shared Trend Row Builder ───────────────────────────────

    private function buildTrendRows($query, string $metric, string $period): array
    {
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $raw = $query
            ->selectRaw("$labelExpr as period_label, $sortExpr as sort_key, SUM(`$metric`) as value")
            ->groupBy('period_label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return $raw->map(fn($r) => [
            'period' => $r->period_label,
            'value'  => (float) $r->value,
        ])->values()->toArray();
    }

    // ── Report Types ───────────────────────────────────────────

    private function getReportTypes(): array
    {
        return [
            ['key' => 'single_dimension',  'label' => 'Single Dimension',   'description' => 'Revenue by one dimension e.g. Branch, Zone'],
            ['key' => 'matrix',            'label' => 'Matrix (2D)',         'description' => 'Two dimensions cross-tabulated e.g. Zone × Product'],
            ['key' => 'ranking',           'label' => 'Branch Product Rank', 'description' => 'Ranks branches per product with drill-down popup'],
            ['key' => 'customer_nature',   'label' => 'Customer Nature',     'description' => 'New, Repeating, Active, Stop, Dead, Reactivated'],
            ['key' => 'period_comparison', 'label' => 'Period Comparison',   'description' => 'Compare two date ranges side by side with % change'],
            ['key' => 'trend',             'label' => 'Trend Over Time',     'description' => 'Monthly, Q1-Q4 or H1-H2 revenue trend'],
            ['key' => 'two_factors_trend', 'label' => 'Two Factors Trend',   'description' => 'e.g. Branch vs Product trend with GR% per period'],
        ];
    }
}