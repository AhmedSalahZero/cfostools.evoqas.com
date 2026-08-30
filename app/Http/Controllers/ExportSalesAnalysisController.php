<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ExportSalesFieldMapping;
use App\Models\ExportSalesUpload;
use App\Models\ExportSalesData;
use App\Models\PortfolioCompany;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportSalesTemplateExport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExportSalesAnalysisController extends Controller
{
    private function authorizeExportSalesCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'export_sales_analysis');
    }

    // ── Field definitions (matches screenshot exactly) ─────────────────
    public const FIELDS = [
        'purchase_order_number'        => 'Purchase Order Number',
        'purchase_order_date'          => 'Purchase Order Date',
        'business_unit'                => 'Business Unit',
        'customer_name'                => 'Customer Name',
        'consignee'                    => 'Consignee',
        'loading_country'              => 'Loading Country',
        'destination_country'          => 'Destination Country',
        'broker'                       => 'Broker',
        'product_category'             => 'Category',
        'product_item'                 => 'Product Item',
        'origin'                       => 'Origin',
        'packing_unit_of_measurement'  => 'Packing Unit Of Measurement',
        'packing_quantity'             => 'Packing Quantity',
        'packing_type'                 => 'Packing Type',
        'full_container_load_count'    => 'Full Container Load Count',
        'full_container_load_type'     => 'Full Container Load Type',
        'quantity_unit_of_measurement' => 'Quantity Unit Of Measurement',
        'quantity'                     => 'Quantity',
        'currency'                     => 'Currency',
        'price_per_unit'               => 'Price Per Unit',
        'purchase_order_value'         => 'Purchase Order Value',
        'purchase_order_net_value'     => 'Purchase Order Net Value',
        'incoterms'                    => 'Incoterms',
        'freight_value'                => 'Freight Value',
        'payment_terms'                => 'Payment Terms',
        'shipping_line'                => 'Shipping Line',
        'booking_number'               => 'Booking Number',
        'port_of_loading'              => 'Port Of Loading [PoL]',
        'cut_off_date'                 => 'Cut Off Date',
        'estimated_time_of_sailing'    => 'Estimated Time of Sailing [ETs]',
        'estimated_time_of_arrival'    => 'Estimated Time of Arrival [ETa]',
        'port_of_destination'          => 'Port Of Destination [PoD]',
        'inspection_company'           => 'Inspection Company',
        'clearance_agent'              => 'Clearance Agent',
        'export_bank'                  => 'Export Bank',
        'documents_sending_type'       => 'Documents Sending Type',
        'purchase_order_status'        => 'Purchase Order Status',
        'revenue_stream'               => 'Revenue Stream',
        'date'                         => 'Date',
    ];

    private const METRIC_FIELDS = [
        'quantity', 'packing_quantity', 'price_per_unit',
        'purchase_order_value', 'purchase_order_net_value', 'freight_value',
        'full_container_load_count',
    ];

    // Price Per Unit isn't meaningfully additive across rows — summing it
    // produces a number with no real meaning, so it's quantity-weighted
    // instead (total value ÷ total units) everywhere it's used.
    private const NON_ADDITIVE_METRICS = ['price_per_unit'];

    private function aggFunc(string $metric): string
    {
        return in_array($metric, self::NON_ADDITIVE_METRICS) ? 'AVG' : 'SUM';
    }

    // Returns the full SQL expression to compute a metric's value.
    private function metricExpr(string $metric): string
    {
        if ($metric === 'price_per_unit') {
            return "SUM(`price_per_unit` * `quantity`) / NULLIF(SUM(`quantity`), 0)";
        }
        return "{$this->aggFunc($metric)}(`$metric`)";
    }

    private const NON_DIMENSION_FIELDS = [
        'quantity', 'packing_quantity', 'price_per_unit',
        'purchase_order_value', 'purchase_order_net_value', 'freight_value',
        'full_container_load_count', 'purchase_order_number',
        'purchase_order_date', 'date', 'cut_off_date',
        'estimated_time_of_sailing', 'estimated_time_of_arrival',
        'booking_number',
    ];

    // ── Helpers ─────────────────────────────────────────────────────────

    private function getActiveFields($companyId): array
    {
        $active = ExportSalesFieldMapping::where('portfolio_company_id', $companyId)
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

    private function getPeriodExpressions(string $period): array
    {
        return match($period) {
            'monthly'      => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
            'quarterly'    => ["CONCAT(YEAR(`date`), '-Q', QUARTER(`date`))", "YEAR(`date`) * 10 + QUARTER(`date`)"],
            'semi_annually'=> ["CONCAT(YEAR(`date`), '-H', IF(MONTH(`date`) <= 6, 1, 2))", "YEAR(`date`) * 10 + IF(MONTH(`date`) <= 6, 1, 2)"],
            'annually'     => ["DATE_FORMAT(`date`, '%Y')", "YEAR(`date`)"],
            default        => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
        };
    }

    // Decode a JSON-encoded array of {from, to} period ranges (2-5 of them).
    // Falls back to date_from/date_to + compare_from/compare_to if "periods"
    // wasn't sent (older clients).
    private function decodePeriods($raw, $request): array
    {
        $periods = [];
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($decoded)) {
            foreach ($decoded as $p) {
                if (!empty($p['from']) && !empty($p['to'])) {
                    $periods[] = ['from' => $p['from'], 'to' => $p['to']];
                }
            }
        }
        if (count($periods) < 2) {
            $periods = [
                ['from' => $request->date_from, 'to' => $request->date_to],
                ['from' => $request->compare_from ?? $request->date_from, 'to' => $request->compare_to ?? $request->date_to],
            ];
        }
        return array_slice($periods, 0, 5);
    }

    // Decode a JSON-encoded array of specific item names hand-picked by the
    // user. Returns [] when nothing was picked (default automatic behavior).
    private function decodeSelectedItems($raw): array
    {
        if (!$raw) return [];
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($decoded)
            ? array_values(array_filter($decoded, fn($v) => $v !== null && $v !== ''))
            : [];
    }

    // ── Field Mapping ────────────────────────────────────────────────────

    public function fieldMapping($companyId)
    {
        $company = $this->authorizeExportSalesCompany($companyId);
        $saved   = ExportSalesFieldMapping::where('portfolio_company_id', $companyId)
            ->pluck('is_active', 'field_key')->toArray();

        $fields = collect(self::FIELDS)->map(function ($label, $key) use ($saved) {
            return [
                'key'       => $key,
                'label'     => $label,
                'is_active' => isset($saved[$key]) ? (bool)$saved[$key] : true,
            ];
        })->values();

        return Inertia::render('ExportSales/FieldMapping', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'fields'  => $fields,
        ]);
    }

    public function saveFieldMapping(Request $request, $companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $request->validate([
            'fields'          => ['required', 'array'],
            'fields.*.key'    => ['required', 'string'],
            'fields.*.active' => ['required', 'boolean'],
        ]);

        foreach ($request->fields as $item) {
            ExportSalesFieldMapping::updateOrCreate(
                ['portfolio_company_id' => $companyId, 'field_key' => $item['key']],
                ['is_active' => $item['active'], 'sort_order' => array_search($item['key'], array_keys(self::FIELDS))]
            );
        }

        return back()->with('flash', ['success' => 'Field mapping saved successfully.']);
    }

    // ── Download Template ────────────────────────────────────────────────

    public function downloadTemplate($companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $activeFields = $this->getActiveFields($companyId);
        $headers      = array_map(fn($key) => self::FIELDS[$key] ?? $key, $activeFields);
        return Excel::download(new ExportSalesTemplateExport($headers), 'export_sales_template.xlsx');
    }

    // ── Upload Page ──────────────────────────────────────────────────────

    public function uploadPage($companyId)
    {
        $company = $this->authorizeExportSalesCompany($companyId);
        $uploads = ExportSalesUpload::where('portfolio_company_id', $companyId)
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

        return Inertia::render('ExportSales/Upload', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'uploads' => $uploads,
        ]);
    }

    // ── Process Upload ───────────────────────────────────────────────────

    public function processUpload(Request $request, $companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $request->validate([
            'file'        => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
            'date_format' => ['required', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY/MM/DD,DD-MM-YYYY,MM-DD-YYYY,YYYY-MM-DD'],
        ]);

        $path   = $request->file('file')->store('export-sales-uploads', 'local');
        $upload = ExportSalesUpload::create([
            'portfolio_company_id' => $companyId,
            'file_path'            => $path,
            'original_filename'    => $request->file('file')->getClientOriginalName(),
            'period_from'          => $request->period_from,
            'period_to'            => $request->period_to,
            'date_format'          => $request->date_format,
            'status'               => 'processing',
            'uploaded_by'          => Auth::id(),
        ]);

        dispatch(new \App\Jobs\ProcessExportSalesUpload($upload->id));

        return back()->with('flash', [
            'success' => 'Processing in background — refresh in a moment to see status.',
        ]);
    }

    public function deleteUpload(PortfolioCompany $company, $upload)
    {
        $this->authorizeCompany($company, 'export_sales_analysis');
        $record = ExportSalesUpload::where('portfolio_company_id', $company->id)->findOrFail($upload);
        ExportSalesData::where('portfolio_company_id', $company->id)->where('upload_id', $upload)->delete();
        $record->delete();
        return response()->json(['success' => true]);
    }

    // ── Reports Page ─────────────────────────────────────────────────────

    public function reportsPage($companyId)
    {
        $company = $this->authorizeExportSalesCompany($companyId);
        $hasData = ExportSalesData::where('portfolio_company_id', $companyId)->exists();

        $activeKeys      = $this->getActiveFields($companyId);
        $activeFields    = array_intersect_key(self::FIELDS, array_flip($activeKeys));

        $dimensionKeys   = $this->getActiveDimensionFields($companyId);
        $dimensionFields = array_intersect_key(self::FIELDS, array_flip($dimensionKeys));

        $metricKeys   = $this->getActiveMetricFields($companyId);
        $metricFields = array_intersect_key(self::FIELDS, array_flip($metricKeys));
        if (empty($metricFields)) {
            $metricFields = ['purchase_order_net_value' => 'Purchase Order Net Value'];
        }

        return Inertia::render('ExportSales/Reports', [
            'company'         => ['id' => $company->id, 'name' => $company->name],
            'hasData'         => $hasData,
            'fields'          => $activeFields,
            'dimensionFields' => $dimensionFields,
            'metricFields'    => $metricFields,
            'reportTypes'     => $this->getReportTypes(),
        ]);
    }

    // ── Run Report ───────────────────────────────────────────────────────

    public function runReport(Request $request, $companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $request->validate([
            'report_type'   => ['required', 'string'],
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date'],
            'period'        => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'    => ['nullable', 'string'],
            'dimension2'    => ['nullable', 'string'],
            'metric'        => ['nullable', 'string'],
            'top_n'         => ['nullable', 'integer', 'min:1', 'max:500'],
            'periods'       => ['nullable'],
            'selected_items'=> ['nullable'],
            'dim1_items'    => ['nullable'],
            'dim2_items'    => ['nullable'],
        ]);

        $query = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
            'customer_nature'   => $this->runCustomerNature($companyId, $request),
            'period_comparison' => $this->runPeriodComparison($companyId, $request),
            'trend'             => $this->runTrend($query, $request),
            'two_factors_trend' => $this->runTwoFactorsTrend($query, $request),
            'po_status'         => $this->runPoStatus($companyId, $request),
            default             => ['error' => 'Unknown report type'],
        };

        return response()->json($result);
    }

    // ── Export Report ────────────────────────────────────────────────────

    public function exportReport(Request $request, $companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $request->validate([
            'report_type'   => ['required', 'string'],
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date'],
            'period'        => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'    => ['nullable', 'string'],
            'dimension2'    => ['nullable', 'string'],
            'metric'        => ['nullable', 'string'],
            'top_n'         => ['nullable', 'integer', 'min:1', 'max:500'],
            'periods'       => ['nullable'],
            'selected_items'=> ['nullable'],
            'dim1_items'    => ['nullable'],
            'dim2_items'    => ['nullable'],
        ]);

        $company = PortfolioCompany::findOrFail($companyId);

        $query = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
            'customer_nature'   => $this->runCustomerNature($companyId, $request),
            'period_comparison' => $this->runPeriodComparison($companyId, $request),
            'trend'             => $this->runTrend($query, $request),
            'two_factors_trend' => $this->runTwoFactorsTrend($query, $request),
            'po_status'         => $this->runPoStatus($companyId, $request),
            default             => [],
        };

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2d5a9e']]],
        ];
        $totalStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0f2040']],
        ];
        $altStyle    = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8fafc']]];
        $numFmt      = '#,##0.00';

        $metricLabel = self::FIELDS[$request->metric ?? 'purchase_order_net_value'] ?? 'Net Value';
        $reportTypes = $this->getReportTypes();
        $reportLabel = collect($reportTypes)->firstWhere('key', $request->report_type)['label'] ?? $request->report_type;

        $sheet->setCellValue('A1', $company->name . ' — Export Sales — ' . $reportLabel);
        $sheet->setCellValue('A2', 'Period: ' . $request->date_from . ' to ' . $request->date_to);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1e3a5f');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('6b7280');
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');

        $row = 4;

        if (($result['type'] ?? '') === 'single_dimension') {
            $dimLabel = self::FIELDS[$result['dimension']] ?? $result['dimension'];
            $sheet->fromArray([$dimLabel, $metricLabel, 'Transactions', '% Share'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            $rows  = collect($result['rows']);
            $total = $rows->sum(fn($r) => floatval($r['value'] ?? 0));
            foreach ($rows as $i => $r) {
                $share = $total > 0 ? round(floatval($r['value']) / $total * 100, 2) : 0;
                $sheet->fromArray([$r['label'], floatval($r['value']), intval($r['transactions']), $share . '%'], null, "A$row");
                $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numFmt);
                if ($i % 2 === 1) $sheet->getStyle("A$row:D$row")->applyFromArray($altStyle);
                $row++;
            }
            $sheet->fromArray(['Total', $total, $rows->sum(fn($r) => intval($r['transactions'])), '100%'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($totalStyle);
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'trend') {
            $sheet->fromArray(['Period', $metricLabel, 'vs Previous %'], null, "A$row");
            $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
            $row++;
            $rows = $result['rows'];
            foreach ($rows as $i => $r) {
                $prev = $i > 0 ? floatval($rows[$i-1]['value']) : null;
                $gr   = ($prev && $prev > 0) ? round((floatval($r['value']) - $prev) / $prev * 100, 2) : null;
                $sheet->fromArray([$r['period'], floatval($r['value']), $gr !== null ? $gr . '%' : '—'], null, "A$row");
                $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numFmt);
                if ($i % 2 === 1) $sheet->getStyle("A$row:C$row")->applyFromArray($altStyle);
                $row++;
            }
            foreach (['A','B','C'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'matrix') {
            $headers = array_merge([self::FIELDS[$result['dim1']] ?? $result['dim1']], $result['columns']->toArray(), ['Total']);
            $sheet->fromArray($headers, null, "A$row");
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$lastCol}$row")->applyFromArray($headerStyle);
            $row++;
            foreach ($result['rows'] as $i => $r) {
                $rowData  = [$r['label']];
                $rowTotal = 0;
                foreach ($result['columns'] as $col) { $v = $r[$col] ?? 0; $rowData[] = $v; $rowTotal += $v; }
                $rowData[] = $rowTotal;
                $sheet->fromArray($rowData, null, "A$row");
                if ($i % 2 === 1) $sheet->getStyle("A$row:{$lastCol}$row")->applyFromArray($altStyle);
                $row++;
            }
            for ($ci = 1; $ci <= count($headers); $ci++) $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'po_status') {
            $sheet->fromArray(['PO Status', 'Count', 'Total Value', '% Share'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            $total = collect($result['rows'])->sum(fn($r) => floatval($r['value']));
            foreach ($result['rows'] as $i => $r) {
                $share = $total > 0 ? round(floatval($r['value']) / $total * 100, 2) : 0;
                $sheet->fromArray([$r['status'], intval($r['count']), floatval($r['value']), $share . '%'], null, "A$row");
                $sheet->getStyle("C$row")->getNumberFormat()->setFormatCode($numFmt);
                if ($i % 2 === 1) $sheet->getStyle("A$row:D$row")->applyFromArray($altStyle);
                $row++;
            }
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'period_comparison') {
            $periods = collect($result['periods']);
            $headers = ['Label'];
            foreach ($periods as $i => $p) {
                $headers[] = 'Period ' . ($i + 1) . ' (' . $p['from'] . ' → ' . $p['to'] . ')';
                if ($i > 0) $headers[] = 'Change %';
            }
            $sheet->fromArray($headers, null, "A$row");
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($headerStyle);
            $row++;
            foreach (collect($result['rows']) as $i => $r) {
                $rowData = [$r['label'] . (!empty($r['is_other']) ? ' (' . $r['other_count'] . ' items)' : '')];
                foreach ($periods as $pi => $p) {
                    $rowData[] = floatval($r['values'][$pi] ?? 0);
                    if ($pi > 0) {
                        $chg = $r['changes'][$pi] ?? null;
                        $rowData[] = $chg !== null ? $chg . '%' : 'N/A';
                    }
                }
                $sheet->fromArray($rowData, null, "A$row");
                if ($i % 2 === 1) $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($altStyle);
                $row++;
            }
            for ($ci = 1; $ci <= count($headers); $ci++) $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'two_factors_trend') {
            $periods = $result['periods'];
            $headers = array_merge(["{$metricLabel} by " . (self::FIELDS[$result['dim1']] ?? $result['dim1']) . ' / ' . (self::FIELDS[$result['dim2']] ?? $result['dim2'])], $periods->toArray(), ['Total']);
            $sheet->fromArray($headers, null, "A$row");
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($headerStyle);
            $row++;
            foreach ($result['rows'] as $parent) {
                $parentLabel = $parent['label'] . (!empty($parent['is_other']) ? ' (' . $parent['other_count'] . ' items)' : '');
                $parentData = [$parentLabel];
                foreach ($periods as $p) $parentData[] = $parent['cells'][$p]['value'] ?? 0;
                $parentData[] = $parent['total'];
                $sheet->fromArray($parentData, null, "A$row");
                $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
                ]);
                $row++;
                foreach ($parent['children'] as $child) {
                    $childLabel = '  ' . $child['label'] . (!empty($child['is_other']) ? ' (' . $child['other_count'] . ' items)' : '');
                    $childData = [$childLabel];
                    foreach ($periods as $p) $childData[] = $child['cells'][$p]['value'] ?? 0;
                    $childData[] = $child['total'];
                    $sheet->fromArray($childData, null, "A$row");
                    $row++;
                }
            }
            for ($ci = 1; $ci <= count($headers); $ci++) $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);

        } elseif (($result['type'] ?? '') === 'customer_nature') {
            $sheet->fromArray(['Customer Category', 'Count', 'Total Value'], null, "A$row");
            $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
            $row++;
            $catLabels = [
                'new' => 'New Customers', 'repeating' => 'Repeating',
                'active' => 'Active (3+ yrs)', 'stop' => 'Stop',
                'dead' => 'Dead', 'stop_reactivated' => 'Stop Reactivated',
                'dead_reactivated' => 'Dead Reactivated',
            ];
            foreach ($result['categories'] as $cat) {
                $sheet->fromArray([$catLabels[$cat['label']] ?? $cat['label'], $cat['count'], floatval($cat['total_sales'])], null, "A$row");
                $sheet->getStyle("C$row")->getNumberFormat()->setFormatCode($numFmt);
                $row++;
            }
            foreach ($result['categories'] as $cat) {
                if (empty($cat['customers'])) continue;
                $catLabel = $catLabels[$cat['label']] ?? $cat['label'];
                $detailSheet = $spreadsheet->createSheet();
                $detailSheet->setTitle(substr($catLabel, 0, 31));
                $detailSheet->fromArray(['Customer Name', 'Value', '% of Total'], null, 'A1');
                $detailSheet->getStyle('A1:C1')->applyFromArray($headerStyle);
                foreach ($cat['customers'] as $i => $c) {
                    $detailSheet->fromArray([$c['name'], floatval($c['sales']), $c['percentage'] . '%'], null, 'A' . ($i + 2));
                    $detailSheet->getStyle('B' . ($i + 2))->getNumberFormat()->setFormatCode($numFmt);
                }
                foreach (['A','B','C'] as $col) $detailSheet->getColumnDimension($col)->setAutoSize(true);
            }
            $spreadsheet->setActiveSheetIndex(0);
            foreach (['A','B','C'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->setTitle(substr($reportLabel, 0, 31));

        $writer   = new Xlsx($spreadsheet);
        $filename = $company->name . '_ExportSales_' . $reportLabel . '_' . $request->date_from . '_' . $request->date_to . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    // ── Report Engines ────────────────────────────────────────────────────

    private function runSingleDimension($query, $request)
    {
        $dimension = $request->dimension1 ?? 'destination_country';
        $metric    = $request->metric ?? 'purchase_order_net_value';
        $limit     = (int) ($request->top_n ?? 300);

        $selected = $this->decodeSelectedItems($request->selected_items ?? null);

        $q = $query->whereNotNull($dimension)->where($dimension, '!=', '');
        if (!empty($selected)) {
            $q->whereIn($dimension, $selected);
        }

        $rows = $q->selectRaw("`$dimension` as label, {$this->metricExpr($metric)} as value, COUNT(*) as transactions")
            ->groupBy($dimension)->orderByDesc('value')->get();

        if (empty($selected) && $rows->count() > $limit) {
            $top  = $rows->take($limit);
            $rest = $rows->slice($limit);
            $result = $top->map(fn($r) => [
                'label' => $r->label, 'value' => (float) $r->value, 'transactions' => (int) $r->transactions,
            ])->values();
            $result->push([
                'label'       => 'Others',
                'value'       => (float) $rest->sum('value'),
                'transactions'=> (int) $rest->sum('transactions'),
                'is_other'    => true,
                'other_count' => $rest->count(),
            ]);
        } else {
            $result = $rows->map(fn($r) => [
                'label' => $r->label, 'value' => (float) $r->value, 'transactions' => (int) $r->transactions,
            ])->values();
        }

        return ['type' => 'single_dimension', 'dimension' => $dimension, 'metric' => $metric, 'rows' => $result];
    }

    private function runMatrix($query, $request)
    {
        $dim1      = $request->dimension1 ?? 'destination_country';
        $dim2      = $request->dimension2 ?? 'product_category';
        $metric    = $request->metric ?? 'purchase_order_net_value';
        $dim1Limit = 300; // rows scroll vertically — can afford many
        $dim2Limit = 20;  // columns force horizontal scroll — keep tight

        $dim1Selected = $this->decodeSelectedItems($request->dim1_items ?? null);
        $dim2Selected = $this->decodeSelectedItems($request->dim2_items ?? null);

        $query->whereNotNull($dim1)->whereNotNull($dim2)
              ->where($dim1, '!=', '')->where($dim2, '!=', '');
        if (!empty($dim1Selected)) $query->whereIn($dim1, $dim1Selected);
        if (!empty($dim2Selected)) $query->whereIn($dim2, $dim2Selected);

        $rows = $query->selectRaw("`$dim1` as dim1, `$dim2` as dim2, {$this->metricExpr($metric)} as value")
            ->groupBy($dim1, $dim2)->get();

        $dim1Totals = [];
        $dim2Totals = [];
        foreach ($rows as $r) {
            $dim1Totals[$r->dim1] = ($dim1Totals[$r->dim1] ?? 0) + (float) $r->value;
            $dim2Totals[$r->dim2] = ($dim2Totals[$r->dim2] ?? 0) + (float) $r->value;
        }
        arsort($dim1Totals);
        arsort($dim2Totals);
        $dim1Keys = array_keys($dim1Totals);
        $dim2Keys = array_keys($dim2Totals);

        $dim1Other = [];
        if (empty($dim1Selected) && count($dim1Keys) > $dim1Limit) {
            $dim1Other = array_slice($dim1Keys, $dim1Limit);
            $dim1Keys  = array_slice($dim1Keys, 0, $dim1Limit);
        }
        $dim2Other = [];
        if (empty($dim2Selected) && count($dim2Keys) > $dim2Limit) {
            $dim2Other = array_slice($dim2Keys, $dim2Limit);
            $dim2Keys  = array_slice($dim2Keys, 0, $dim2Limit);
        }

        $dim1OtherLabel = !empty($dim1Other) ? 'Others (' . count($dim1Other) . ' items)' : null;
        $dim2OtherLabel = !empty($dim2Other) ? 'Others (' . count($dim2Other) . ' items)' : null;
        $dim1OtherSet   = array_flip($dim1Other);
        $dim2OtherSet   = array_flip($dim2Other);

        $grid = [];
        foreach ($rows as $r) {
            $b1 = $dim1OtherLabel && isset($dim1OtherSet[$r->dim1]) ? $dim1OtherLabel : $r->dim1;
            $b2 = $dim2OtherLabel && isset($dim2OtherSet[$r->dim2]) ? $dim2OtherLabel : $r->dim2;
            $grid[$b1][$b2] = ($grid[$b1][$b2] ?? 0) + (float) $r->value;
        }

        $rowOrder = $dim1Keys;
        if ($dim1OtherLabel) $rowOrder[] = $dim1OtherLabel;
        $colOrder = $dim2Keys;
        if ($dim2OtherLabel) $colOrder[] = $dim2OtherLabel;

        $matrix = [];
        foreach ($rowOrder as $d1) {
            $row = ['label' => $d1];
            foreach ($colOrder as $d2) {
                $row[$d2] = $grid[$d1][$d2] ?? 0;
            }
            $matrix[] = $row;
        }

        return [
            'type'    => 'matrix',
            'dim1'    => $dim1,
            'dim2'    => $dim2,
            'metric'  => $metric,
            'columns' => collect($colOrder),
            'rows'    => $matrix,
        ];
    }

    private function runRanking($query, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';
        $dim    = $request->dimension1 ?? 'product_category';

        $data = $query->whereNotNull('destination_country')->whereNotNull($dim)
            ->where('destination_country', '!=', '')->where($dim, '!=', '')
            ->selectRaw("`destination_country` as branch, `$dim` as product_dim, {$this->metricExpr($metric)} as value")
            ->groupBy('destination_country', $dim)->get();

        $branches = $data->pluck('branch')->unique()->sort()->values();
        $products = $data->pluck('product_dim')->unique()->values();
        $numBranches = $branches->count();

        $rankMap = [];
        foreach ($products as $product) {
            $productRows = $data->where('product_dim', $product)->sortByDesc('value')->values();
            foreach ($productRows as $i => $row) {
                $rank   = $i + 1;
                $branch = $row->branch;
                $rankMap[$branch][$rank][] = ['product' => $product, 'value' => (float)$row->value];
            }
        }

        $rows = [];
        foreach ($branches as $branch) {
            $rankCells = [];
            for ($r = 1; $r <= $numBranches; $r++) {
                $items         = $rankMap[$branch][$r] ?? [];
                $rankCells[$r] = ['count' => count($items), 'products' => $items, 'total' => array_sum(array_column($items, 'value'))];
            }
            $rows[] = ['branch' => $branch, 'ranks' => $rankCells];
        }

        return ['type' => 'ranking', 'metric' => $metric, 'dimension' => $dim, 'num_ranks' => $numBranches, 'branches' => $branches->values(), 'rows' => $rows];
    }

    private function runPeriodComparison($companyId, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';
        $dim    = $request->dimension1 ?? 'destination_country';
        $limit  = 300;

        $periods  = $this->decodePeriods($request->periods ?? null, $request);
        $selected = $this->decodeSelectedItems($request->selected_items ?? null);
        $lastIdx  = count($periods) - 1;

        $perPeriodData = [];
        foreach ($periods as $i => $p) {
            $q = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$p['from'], $p['to']])
                ->whereNotNull($dim)->where($dim, '!=', '');
            if (!empty($selected)) {
                $q->whereIn($dim, $selected);
            }
            $perPeriodData[$i] = $q->selectRaw("`$dim` as label, {$this->metricExpr($metric)} as value")
                ->groupBy($dim)->get()->keyBy('label');
        }

        $allLabels = collect();
        foreach ($perPeriodData as $data) {
            $allLabels = $allLabels->merge($data->keys());
        }
        $allLabels = $allLabels->unique()->values();

        $rows = $allLabels->map(function ($label) use ($perPeriodData, $lastIdx) {
            $values = [];
            foreach ($perPeriodData as $i => $data) {
                $values[$i] = (float) ($data[$label]->value ?? 0);
            }
            $changes = [];
            foreach ($values as $i => $v) {
                if ($i === 0) { $changes[$i] = null; continue; }
                $prev = $values[$i - 1];
                $changes[$i] = $prev > 0 ? round(($v - $prev) / $prev * 100, 2) : null;
            }
            return [
                'label'      => $label,
                'values'     => $values,
                'changes'    => $changes,
                'sort_value' => $values[$lastIdx],
            ];
        });

        $rows = $rows->sortByDesc('sort_value')->values();

        if (empty($selected) && $rows->count() > $limit) {
            $top  = $rows->take($limit);
            $rest = $rows->slice($limit);

            $otherValues = [];
            foreach ($periods as $i => $p) {
                $otherValues[$i] = $rest->sum(fn($r) => $r['values'][$i]);
            }
            $otherChanges = [];
            foreach ($otherValues as $i => $v) {
                if ($i === 0) { $otherChanges[$i] = null; continue; }
                $prev = $otherValues[$i - 1];
                $otherChanges[$i] = $prev > 0 ? round(($v - $prev) / $prev * 100, 2) : null;
            }

            $rows = $top->values();
            $rows->push([
                'label'       => 'Others',
                'values'      => $otherValues,
                'changes'     => $otherChanges,
                'is_other'    => true,
                'other_count' => $rest->count(),
            ]);
        }

        return [
            'type'      => 'period_comparison',
            'periods'   => collect($periods)->map(fn($p, $i) => ['index' => $i, 'from' => $p['from'], 'to' => $p['to']])->values(),
            'dimension' => $dim,
            'metric'    => $metric,
            'rows'      => $rows,
        ];
    }

    private function runTrend($query, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';
        $period = $request->period ?? 'monthly';
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $rows = $query->selectRaw("$labelExpr as period_label, $sortExpr as sort_key, {$this->metricExpr($metric)} as value")
            ->groupBy('period_label', 'sort_key')->orderBy('sort_key')->get()
            ->map(fn($r) => ['period' => $r->period_label, 'value' => (float)$r->value])->values()->toArray();

        return ['type' => 'trend', 'metric' => $metric, 'period' => $period, 'rows' => $rows];
    }

    private function runTwoFactorsTrend($query, $request)
    {
        $dim1   = $request->dimension1 ?? 'destination_country';
        $dim2   = $request->dimension2 ?? 'product_category';
        $metric = $request->metric ?? 'purchase_order_net_value';
        $period = $request->period ?? 'monthly';
        $limit  = 300;

        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $dim1Selected = $this->decodeSelectedItems($request->dim1_items ?? null);
        $dim2Selected = $this->decodeSelectedItems($request->dim2_items ?? null);

        $query->whereNotNull($dim1)->where($dim1, '!=', '')
              ->whereNotNull($dim2)->where($dim2, '!=', '');
        if (!empty($dim1Selected)) $query->whereIn($dim1, $dim1Selected);
        if (!empty($dim2Selected)) $query->whereIn($dim2, $dim2Selected);

        $data = $query
            ->selectRaw("`$dim1` as dim1, `$dim2` as dim2, $labelExpr as period_label, $sortExpr as sort_key, {$this->metricExpr($metric)} as value")
            ->groupBy('dim1', 'dim2', 'period_label', 'sort_key')
            ->get();

        $periods = $data->sortBy('sort_key')->pluck('period_label')->unique()->values();

        // dim1 totals, used to rank (largest → smallest) and to decide
        // which categories fold into "Others" (Top 300 overall). dim2 is
        // intentionally NOT capped here — that's applied per-category below.
        $dim1Totals = [];
        $grouped    = [];
        foreach ($data as $row) {
            $dim1Totals[$row->dim1] = ($dim1Totals[$row->dim1] ?? 0) + (float) $row->value;
            $grouped[$row->dim1][$row->dim2][$row->period_label] =
                ($grouped[$row->dim1][$row->dim2][$row->period_label] ?? 0) + (float) $row->value;
        }
        arsort($dim1Totals);

        $dim1Keys = array_keys($dim1Totals);

        $dim1Other = [];
        if (empty($dim1Selected) && count($dim1Keys) > $limit) {
            $dim1Other = array_slice($dim1Keys, $limit);
            $dim1Keys  = array_slice($dim1Keys, 0, $limit);
        }

        $buildCells = function (array $periodValues) use ($periods) {
            $cells = [];
            $prev  = null;
            foreach ($periods as $p) {
                $val       = $periodValues[$p] ?? 0;
                $gr        = ($prev !== null && $prev > 0) ? round(($val - $prev) / $prev * 100, 1) : 0;
                $cells[$p] = ['value' => $val, 'gr' => $gr];
                $prev      = $val;
            }
            return $cells;
        };

        $resultRows = [];
        foreach ($dim1Keys as $d1) {
            $dim2Groups = $grouped[$d1] ?? [];

            // Rank & cap Factor 2 items WITHIN this category only.
            $d2Totals = [];
            foreach ($dim2Groups as $d2 => $periodData) {
                $d2Totals[$d2] = array_sum($periodData);
            }
            arsort($d2Totals);
            $d2Keys = array_keys($d2Totals);

            $d2Other = [];
            if (empty($dim2Selected) && count($d2Keys) > $limit) {
                $d2Other = array_slice($d2Keys, $limit);
                $d2Keys  = array_slice($d2Keys, 0, $limit);
            }
            $d2OtherSet = array_flip($d2Other);

            $childRows        = [];
            $otherChildTotals = [];
            $otherChildCount  = 0;
            foreach ($dim2Groups as $d2 => $periodData) {
                if (isset($d2OtherSet[$d2])) {
                    $otherChildCount++;
                    foreach ($periodData as $p => $v) {
                        $otherChildTotals[$p] = ($otherChildTotals[$p] ?? 0) + $v;
                    }
                    continue;
                }
                $cells = $buildCells($periodData);
                $childRows[] = [
                    'label' => $d2,
                    'cells' => $cells,
                    'total' => array_sum(array_column($cells, 'value')),
                ];
            }
            usort($childRows, fn($a, $b) => $b['total'] <=> $a['total']);

            if (!empty($otherChildTotals)) {
                $cells = $buildCells($otherChildTotals);
                $childRows[] = [
                    'label'       => 'Others',
                    'cells'       => $cells,
                    'total'       => array_sum(array_column($cells, 'value')),
                    'is_other'    => true,
                    'other_count' => $otherChildCount,
                ];
            }

            $parentPeriodTotals = [];
            foreach ($dim2Groups as $periodData) {
                foreach ($periodData as $p => $v) {
                    $parentPeriodTotals[$p] = ($parentPeriodTotals[$p] ?? 0) + $v;
                }
            }
            $parentCells = $buildCells($parentPeriodTotals);

            $resultRows[] = [
                'label'    => $d1,
                'cells'    => $parentCells,
                'total'    => array_sum(array_column($parentCells, 'value')),
                'children' => $childRows,
            ];
        }

        // A single "Others" parent row combining every dim1 item beyond Top 300.
        if (!empty($dim1Other)) {
            $otherParentTotals = [];
            foreach ($dim1Other as $d1) {
                foreach ($grouped[$d1] ?? [] as $periodData) {
                    foreach ($periodData as $p => $v) {
                        $otherParentTotals[$p] = ($otherParentTotals[$p] ?? 0) + $v;
                    }
                }
            }
            $cells = $buildCells($otherParentTotals);
            $resultRows[] = [
                'label'       => 'Others',
                'cells'       => $cells,
                'total'       => array_sum(array_column($cells, 'value')),
                'children'    => [],
                'is_other'    => true,
                'other_count' => count($dim1Other),
            ];
        }

        return ['type' => 'two_factors_trend', 'dim1' => $dim1, 'dim2' => $dim2, 'metric' => $metric, 'period' => $period, 'periods' => $periods, 'rows' => $resultRows];
    }

    // ── Customer Nature ──────────────────────────────────────────────────

    private function runCustomerNature($companyId, $request)
    {
        $currentYear = (int) date('Y', strtotime($request->date_to));
        $metric      = $request->metric ?? 'purchase_order_net_value';

        // Y, Y-1, Y-2, Y-3, Y-4 — need back to Y-4 because "Repeating" checks
        // whether Y-1 was itself that customer's New year (looks back 3
        // years from Y-1, i.e. as far as Y-4).
        $years = [];
        for ($i = 0; $i <= 4; $i++) {
            $y = $currentYear - $i;
            $years[$i] = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereYear('date', $y)->whereNotNull('customer_name')
                ->pluck('customer_name')->unique();
        }
        [$setY, $setY1, $setY2, $setY3, $setY4] = $years;

        $buckets = [
            'new'              => $setY->diff($setY1)->diff($setY2)->diff($setY3)->values(),
            'repeating'        => $setY->intersect($setY1)->diff($setY2)->diff($setY3)->diff($setY4)->values(),
            'active'           => $setY->intersect($setY1)->intersect($setY2)->values(),
            'stop'             => $setY1->diff($setY)->values(),
            'dead'             => $setY2->diff($setY1)->diff($setY)->values(),
            'stop_reactivated' => $setY->intersect($setY2)->diff($setY1)->values(),
            'dead_reactivated' => $setY->intersect($setY3)->diff($setY2)->diff($setY1)->values(),
        ];

        $salesByCustomer = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')
            ->get()
            ->keyBy('customer_name');

        // "Stop" and "Dead" customers have zero sales in the selected
        // period by definition — show their sales from the last year they
        // were genuinely active instead, so the user can see how much
        // revenue is actually being lost.
        $salesLastYear = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 1)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $salesTwoYearsAgo = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 2)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $pastPeriodSales = ['stop' => $salesLastYear, 'dead' => $salesTwoYearsAgo];
        $pastPeriodYear  = ['stop' => $currentYear - 1, 'dead' => $currentYear - 2];

        $grandTotal = $salesByCustomer->sum('total_sales');

        $categories = collect($buckets)->map(function ($customers, $key) use ($pastPeriodSales, $pastPeriodYear, $salesByCustomer, $grandTotal) {
            $isPastPeriod = isset($pastPeriodSales[$key]);
            $salesMap     = $pastPeriodSales[$key] ?? $salesByCustomer;

            $rows = $customers->map(function ($name) use ($salesMap) {
                return ['name' => $name, 'sales' => (float) ($salesMap[$name]->total_sales ?? 0)];
            })->sortByDesc('sales')->values();

            $percentBase = $isPastPeriod ? $rows->sum('sales') : $grandTotal;
            $rows = $rows->map(function ($r) use ($percentBase) {
                $r['percentage'] = $percentBase > 0 ? round($r['sales'] / $percentBase * 100, 2) : 0;
                return $r;
            });

            return [
                'label'             => $key,
                'count'             => $customers->count(),
                'total_sales'       => $rows->sum('sales'),
                'is_past_period'    => $isPastPeriod,
                'sales_period_year' => $isPastPeriod ? $pastPeriodYear[$key] : null,
                'customers'         => $rows,
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

    // ── PO Status Summary (export-specific report) ───────────────────────

    private function runPoStatus($companyId, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';

        $rows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull('purchase_order_status')
            ->where('purchase_order_status', '!=', '')
            ->selectRaw("purchase_order_status as status, COUNT(*) as count, {$this->metricExpr($metric)} as value")
            ->groupBy('purchase_order_status')->orderByDesc('value')->get();

        return ['type' => 'po_status', 'metric' => $metric, 'rows' => $rows];
    }

    private function getReportTypes(): array
    {
        return [
            ['key' => 'single_dimension',  'label' => 'Single Dimension',         'description' => 'Value by one dimension e.g. Destination Country, Product'],
            ['key' => 'matrix',            'label' => 'Matrix (2D)',               'description' => 'Two dimensions cross-tabulated e.g. Country × Product'],
            ['key' => 'ranking',           'label' => 'Country Product Rank',      'description' => 'Ranks destination countries per product'],
            ['key' => 'customer_nature',   'label' => 'Customer Nature',           'description' => 'New, Repeating, Active, Stop, Dead, Reactivated'],
            ['key' => 'period_comparison', 'label' => 'Period Comparison',         'description' => 'Compare two date ranges side by side'],
            ['key' => 'trend',             'label' => 'Trend Over Time',           'description' => 'Monthly, quarterly or annual value trend'],
            ['key' => 'two_factors_trend', 'label' => 'Two Factors Trend',        'description' => 'e.g. Country vs Product trend with GR% per period'],
            ['key' => 'po_status',         'label' => 'PO Status Summary',         'description' => 'Purchase Order status breakdown with value totals'],
        ];
    }
}