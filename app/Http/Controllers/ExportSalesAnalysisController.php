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
            'report_type' => ['required', 'string'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
            'period'      => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'  => ['nullable', 'string'],
            'dimension2'  => ['nullable', 'string'],
            'metric'      => ['nullable', 'string'],
            'top_n'       => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
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
            'report_type' => ['required', 'string'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
            'period'      => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'dimension1'  => ['nullable', 'string'],
            'dimension2'  => ['nullable', 'string'],
            'metric'      => ['nullable', 'string'],
            'top_n'       => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $company = PortfolioCompany::findOrFail($companyId);

        $query = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to]);

        $result = match($request->report_type) {
            'single_dimension'  => $this->runSingleDimension($query, $request),
            'matrix'            => $this->runMatrix($query, $request),
            'ranking'           => $this->runRanking($query, $request),
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
        $topN      = $request->top_n ?? 50;

        $rows = $query->whereNotNull($dimension)->where($dimension, '!=', '')
            ->selectRaw("`$dimension` as label, SUM(`$metric`) as value, COUNT(*) as transactions")
            ->groupBy($dimension)->orderByDesc('value')->limit($topN)->get();

        return ['type' => 'single_dimension', 'dimension' => $dimension, 'metric' => $metric, 'rows' => $rows];
    }

    private function runMatrix($query, $request)
    {
        $dim1   = $request->dimension1 ?? 'destination_country';
        $dim2   = $request->dimension2 ?? 'product_category';
        $metric = $request->metric ?? 'purchase_order_net_value';

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
                $row[$d2] = $found ? (float)$found->value : 0;
            }
            $matrix[] = $row;
        }

        return ['type' => 'matrix', 'dim1' => $dim1, 'dim2' => $dim2, 'metric' => $metric, 'columns' => $dim2Values, 'rows' => $matrix];
    }

    private function runRanking($query, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';
        $dim    = $request->dimension1 ?? 'product_category';

        $data = $query->whereNotNull('destination_country')->whereNotNull($dim)
            ->where('destination_country', '!=', '')->where($dim, '!=', '')
            ->selectRaw("`destination_country` as branch, `$dim` as product_dim, SUM(`$metric`) as value")
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

        $period1 = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull($dim)->where($dim, '!=', '')
            ->selectRaw("`$dim` as label, SUM(`$metric`) as value")
            ->groupBy($dim)->get()->keyBy('label');

        $period2 = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->compare_from, $request->compare_to])
            ->whereNotNull($dim)->where($dim, '!=', '')
            ->selectRaw("`$dim` as label, SUM(`$metric`) as value")
            ->groupBy($dim)->get()->keyBy('label');

        $allLabels = $period1->keys()->merge($period2->keys())->unique()->values();

        $rows = $allLabels->map(function ($label) use ($period1, $period2) {
            $v1 = (float)($period1[$label]->value ?? 0);
            $v2 = (float)($period2[$label]->value ?? 0);
            return ['label' => $label, 'period1' => $v1, 'period2' => $v2, 'change' => $v1 > 0 ? round(($v2 - $v1) / $v1 * 100, 2) : null];
        });

        return [
            'type'    => 'period_comparison',
            'period1' => ['from' => $request->date_from, 'to' => $request->date_to],
            'period2' => ['from' => $request->compare_from, 'to' => $request->compare_to],
            'metric'  => $metric,
            'rows'    => $rows,
        ];
    }

    private function runTrend($query, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';
        $period = $request->period ?? 'monthly';
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $rows = $query->selectRaw("$labelExpr as period_label, $sortExpr as sort_key, SUM(`$metric`) as value")
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

        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $data = $query
            ->whereNotNull($dim1)->where($dim1, '!=', '')
            ->whereNotNull($dim2)->where($dim2, '!=', '')
            ->selectRaw("`$dim1` as dim1, `$dim2` as dim2, $labelExpr as period_label, $sortExpr as sort_key, SUM(`$metric`) as value")
            ->groupBy('dim1', 'dim2', 'period_label', 'sort_key')
            ->orderBy('dim1')->orderBy('dim2')->orderBy('sort_key')->get();

        $periods = $data->sortBy('sort_key')->pluck('period_label')->unique()->values();

        $grouped = [];
        foreach ($data as $row) {
            $grouped[$row->dim1][$row->dim2][$row->period_label] = (float)$row->value;
        }

        $resultRows = [];
        foreach ($grouped as $d1 => $dim2Groups) {
            $subRows = [];
            foreach ($dim2Groups as $d2 => $periodData) {
                $cells = [];
                $prev  = null;
                foreach ($periods as $p) {
                    $val       = $periodData[$p] ?? 0;
                    $gr        = ($prev !== null && $prev > 0) ? round(($val - $prev) / $prev * 100, 1) : 0;
                    $cells[$p] = ['value' => $val, 'gr' => $gr];
                    $prev      = $val;
                }
                $subRows[] = ['label' => $d2, 'cells' => $cells, 'total' => array_sum(array_column($cells, 'value'))];
            }

            $parentCells = [];
            $prev = null;
            foreach ($periods as $p) {
                $val              = collect($dim2Groups)->sum(fn($d) => $d[$p] ?? 0);
                $gr               = ($prev !== null && $prev > 0) ? round(($val - $prev) / $prev * 100, 1) : 0;
                $parentCells[$p]  = ['value' => $val, 'gr' => $gr];
                $prev             = $val;
            }

            $resultRows[] = [
                'label'    => $d1,
                'cells'    => $parentCells,
                'total'    => array_sum(array_column($parentCells, 'value')),
                'children' => $subRows,
            ];
        }

        return ['type' => 'two_factors_trend', 'dim1' => $dim1, 'dim2' => $dim2, 'metric' => $metric, 'period' => $period, 'periods' => $periods, 'rows' => $resultRows];
    }

    // ── PO Status Summary (export-specific report) ───────────────────────

    private function runPoStatus($companyId, $request)
    {
        $metric = $request->metric ?? 'purchase_order_net_value';

        $rows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull('purchase_order_status')
            ->where('purchase_order_status', '!=', '')
            ->selectRaw("purchase_order_status as status, COUNT(*) as count, SUM(`$metric`) as value")
            ->groupBy('purchase_order_status')->orderByDesc('value')->get();

        return ['type' => 'po_status', 'metric' => $metric, 'rows' => $rows];
    }

    private function getReportTypes(): array
    {
        return [
            ['key' => 'single_dimension',  'label' => 'Single Dimension',         'description' => 'Value by one dimension e.g. Destination Country, Product'],
            ['key' => 'matrix',            'label' => 'Matrix (2D)',               'description' => 'Two dimensions cross-tabulated e.g. Country × Product'],
            ['key' => 'ranking',           'label' => 'Country Product Rank',      'description' => 'Ranks destination countries per product'],
            ['key' => 'period_comparison', 'label' => 'Period Comparison',         'description' => 'Compare two date ranges side by side'],
            ['key' => 'trend',             'label' => 'Trend Over Time',           'description' => 'Monthly, quarterly or annual value trend'],
            ['key' => 'two_factors_trend', 'label' => 'Two Factors Trend',        'description' => 'e.g. Country vs Product trend with GR% per period'],
            ['key' => 'po_status',         'label' => 'PO Status Summary',         'description' => 'Purchase Order status breakdown with value totals'],
        ];
    }
}