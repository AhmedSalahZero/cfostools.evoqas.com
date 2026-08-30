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

    // These fields aren't meaningfully additive across rows (summing a
    // per-unit price or a birth year produces a number with no real
    // meaning) — they're averaged instead of summed everywhere.
    private const NON_ADDITIVE_METRICS = ['price_per_unit', 'service_provider_birth_year'];

    private function aggFunc(string $metric): string
    {
        return in_array($metric, self::NON_ADDITIVE_METRICS) ? 'AVG' : 'SUM';
    }

    // Returns the full SQL expression to compute a metric's value.
    private function metricExpr(string $metric): string
    {
        if ($metric === 'price_per_unit') {
            // Quantity-weighted average: total value ÷ total units, not a
            // simple average of per-row unit prices — a row selling 500
            // units should count 500x more than a row selling 1 unit.
            return "SUM(`price_per_unit` * `quantity`) / NULLIF(SUM(`quantity`), 0)";
        }
        return "{$this->aggFunc($metric)}(`$metric`)";
    }

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

    // ── Multi-Period / Multi-Select Helpers ────────────────────

    /**
     * Decode a JSON-encoded array of {from, to} period ranges sent by the
     * frontend. Falls back to the legacy date_from/date_to + compare_from/
     * compare_to fields if "periods" wasn't sent (older clients / exports).
     * Always returns between 2 and 5 periods, oldest-to-newest as entered.
     */
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

    /**
     * Decode a JSON-encoded array of specific item names the user picked in
     * a multi-selector. Returns [] when nothing was picked (i.e. "Select All").
     */
    private function decodeSelectedItems($raw): array
    {
        if (!$raw) return [];
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($decoded)
            ? array_values(array_filter($decoded, fn($v) => $v !== null && $v !== ''))
            : [];
    }

    /**
     * List the items of a dimension (e.g. customer names) sorted by sales
     * value, high → low, for populating a multi-selector. Used both for the
     * default "top 500" list shown on open, and for the live search box.
     */
    public function dimensionItems(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'dimension' => ['required', 'string'],
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
            'metric'    => ['nullable', 'string'],
            'search'    => ['nullable', 'string', 'max:100'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $dimension = $request->dimension;
        if (!array_key_exists($dimension, self::FIELDS) || in_array($dimension, self::NON_DIMENSION_FIELDS)) {
            return response()->json(['items' => []]);
        }

        $metric = $request->metric ?? 'net_sales_value';
        $limit  = (int) ($request->limit ?? 500);

        $query = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull($dimension)->where($dimension, '!=', '');

        $search = trim((string) $request->search);
        if ($search !== '') {
            $query->where($dimension, 'like', '%' . $search . '%');
            $limit = min($limit, 50); // search results stay small/snappy
        }

        $rows = $query->selectRaw("`$dimension` as label, {$this->metricExpr($metric)} as value")
            ->groupBy($dimension)
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $rows->map(fn($r) => ['label' => $r->label, 'value' => (float) $r->value])->values(),
        ]);
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
            'metric'        => ['nullable', 'string'],
            'top_n'         => ['nullable', 'integer', 'min:1', 'max:500'],
            'periods'       => ['nullable'],  // array of {from,to} (2-5) OR a JSON string
            'selected_items'=> ['nullable'],  // array of item names OR a JSON string
            'dim1_items'    => ['nullable'],  // array of Factor 1 item names OR a JSON string
            'dim2_items'    => ['nullable'],  // array of Factor 2 item names OR a JSON string
            'invoice_view'      => ['nullable', 'in:by_dimension,snapshot,large_invoices'],
            'invoice_threshold' => ['nullable', 'numeric', 'min:0'],
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
            'invoice_analysis'  => $this->runInvoiceAnalysis($companyId, $request),
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
            'metric'        => ['nullable', 'string'],
            'top_n'         => ['nullable', 'integer', 'min:1', 'max:500'],
            'periods'       => ['nullable'],
            'selected_items'=> ['nullable'],
            'dim1_items'    => ['nullable'],
            'dim2_items'    => ['nullable'],
            'invoice_view'      => ['nullable', 'in:by_dimension,snapshot,large_invoices'],
            'invoice_threshold' => ['nullable', 'numeric', 'min:0'],
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
            'invoice_analysis'  => $this->runInvoiceAnalysis($companyId, $request),
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
            $sheet->fromArray([$dimLabel, $metricLabel, 'Transactions', '% Share', 'Accumulated % Share'], null, "A$row");
            $sheet->getStyle("A$row:E$row")->applyFromArray($headerStyle);
            $row++;
            $rows = collect($result['rows']);
            $total = $rows->sum(fn($r) => floatval($r['value'] ?? 0));
            $accumulated = 0;
            foreach ($rows as $i => $r) {
                $share = $total > 0 ? round(floatval($r['value']) / $total * 100, 2) : 0;
                $accumulated += $share;
                $sheet->fromArray([$r['label'], floatval($r['value']), intval($r['transactions']), $share . '%', round($accumulated, 1) . '%'], null, "A$row");
                $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:E$row")->applyFromArray($altStyle);
                $row++;
            }
            $sheet->fromArray(['Total', $total, $rows->sum(fn($r) => intval($r['transactions'])), '100%', '100%'], null, "A$row");
            $sheet->getStyle("A$row:E$row")->applyFromArray($totalStyle);
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode($numberFormat);
            foreach (['A','B','C','D','E'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'matrix') {
            $dim1Label = self::FIELDS[$result['dim1']] ?? $result['dim1'];
            $columns   = $result['columns'];
            $headers   = array_merge([$dim1Label], $columns, ['Total']);
            $sheet->fromArray($headers, null, "A$row");
            $sheet->getStyle("A$row:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)->applyFromArray($headerStyle);
            $row++;
            $colTotals = array_fill_keys($columns, 0);
            foreach ($result['rows'] as $i => $r) {
                $rowData = [$r['label']];
                $rowTotal = 0;
                foreach ($columns as $col) { $v = $r[$col] ?? 0; $rowData[] = $v; $rowTotal += $v; $colTotals[$col] += $v; }
                $rowData[] = $rowTotal;
                $sheet->fromArray($rowData, null, "A$row");
                if ($i % 2 === 1) $sheet->getStyle("A$row:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)->applyFromArray($altStyle);
                $row++;
            }
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $totalRow = ['Total'];
            $grandTotal = 0;
            foreach ($columns as $col) { $totalRow[] = $colTotals[$col]; $grandTotal += $colTotals[$col]; }
            $totalRow[] = $grandTotal;
            $sheet->fromArray($totalRow, null, "A$row");
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($totalStyle);
            $row++;
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
            $periods = collect($result['periods']);
            $headers = ['Label'];
            foreach ($periods as $i => $p) {
                $headers[] = 'Period ' . ($i + 1) . ' (' . $p['from'] . ' → ' . $p['to'] . ')';
                if ($i > 0) $headers[] = 'Change %';
                $headers[] = 'Rank';
            }
            $sheet->fromArray($headers, null, "A$row");
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($headerStyle);
            $row++;
            $periodTotals = array_fill(0, $periods->count(), 0);
            foreach (collect($result['rows']) as $i => $r) {
                $rowData = [$r['label'] . (!empty($r['is_other']) ? ' (' . $r['other_count'] . ' items)' : '')];
                foreach ($periods as $pi => $p) {
                    $val = floatval($r['values'][$pi] ?? 0);
                    $periodTotals[$pi] += $val;
                    $rowData[] = $val;
                    if ($pi > 0) {
                        $chg = $r['changes'][$pi] ?? null;
                        $rowData[] = $chg !== null ? $chg . '%' : 'N/A';
                    }
                    $rankInfo = $r['ranks'][$pi] ?? null;
                    if ($rankInfo && $rankInfo['rank'] !== null) {
                        $rankStr = '#' . $rankInfo['rank'] . ' of ' . $rankInfo['total'];
                        if (!empty($r['ranks'][$pi]['rank_change'])) {
                            $rc = $r['ranks'][$pi]['rank_change'];
                            $rankStr .= ' (' . ($rc > 0 ? '+' : '') . $rc . ')';
                        }
                        $rowData[] = $rankStr;
                    } else {
                        $rowData[] = '—';
                    }
                }
                $sheet->fromArray($rowData, null, "A$row");
                if ($i % 2 === 1) $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($altStyle);
                $row++;
            }
            $totalRow = ['Total'];
            foreach ($periods as $pi => $p) {
                $totalRow[] = $periodTotals[$pi];
                if ($pi > 0) {
                    $prev = $periodTotals[$pi - 1];
                    $chg  = $prev > 0 ? round(($periodTotals[$pi] - $prev) / $prev * 100, 2) . '%' : 'N/A';
                    $totalRow[] = $chg;
                }
                $totalRow[] = '—';
            }
            $sheet->fromArray($totalRow, null, "A$row");
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($totalStyle);
            $row++;
            for ($ci = 1; $ci <= count($headers); $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
            }

        } elseif ($result['type'] === 'customer_nature') {
            $sheet->fromArray(['Customer Category', 'Count', 'Total Sales'], null, "A$row");
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
            $lastPeriod = $periods->last();
            $headers   = array_merge(["$dim1Label / $dim2Label"], $periods->toArray(), ['Total', 'Latest Rank']);
            $sheet->fromArray($headers, null, "A$row");
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($headerStyle);
            $row++;
            $periodGrandTotals = array_fill_keys($periods->toArray(), 0);
            $grandTotal = 0;
            foreach ($result['rows'] as $parent) {
                // Parent row
                $parentLabel = $parent['label'] . (!empty($parent['is_other']) ? ' (' . $parent['other_count'] . ' items)' : '');
                $parentData = [$parentLabel];
                foreach ($periods as $p) {
                    $v = $parent['cells'][$p]['value'] ?? 0;
                    $parentData[] = $v;
                    $periodGrandTotals[$p] += $v;
                }
                $parentData[] = $parent['total'];
                $rankInfo = $parent['cells'][$lastPeriod]['rank'] ?? null;
                if ($rankInfo !== null) {
                    $rankStr = '#' . $rankInfo . ' of ' . ($parent['cells'][$lastPeriod]['rank_total'] ?? '?');
                    $rc = $parent['cells'][$lastPeriod]['rank_change'] ?? null;
                    if (!empty($rc)) $rankStr .= ' (' . ($rc > 0 ? '+' : '') . $rc . ')';
                    $parentData[] = $rankStr;
                } else {
                    $parentData[] = '—';
                }
                $grandTotal += $parent['total'];
                $sheet->fromArray($parentData, null, "A$row");
                $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
                ]);
                $row++;
                // Child rows
                foreach ($parent['children'] as $child) {
                    $childLabel = '  ' . $child['label'] . (!empty($child['is_other']) ? ' (' . $child['other_count'] . ' items)' : '');
                    $childData = [$childLabel];
                    foreach ($periods as $p) $childData[] = $child['cells'][$p]['value'] ?? 0;
                    $childData[] = $child['total'];
                    $childData[] = ''; // rank only applies to parent rows
                    $sheet->fromArray($childData, null, "A$row");
                    $row++;
                }
            }
            // Grand Total row — summed from parent (top-level) rows only, so
            // child rows already folded into their parent aren't double-counted.
            $totalRow = ['Total'];
            foreach ($periods as $p) $totalRow[] = $periodGrandTotals[$p];
            $totalRow[] = $grandTotal;
            $totalRow[] = '—';
            $sheet->fromArray($totalRow, null, "A$row");
            $sheet->getStyle("A$row:{$maxCol}$row")->applyFromArray($totalStyle);
            $row++;
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

        } elseif ($result['type'] === 'invoice_analysis' && $result['view'] === 'by_dimension') {
            $dimLabel = self::FIELDS[$result['dimension']] ?? $result['dimension'];
            $sheet->fromArray([$dimLabel, 'Invoice Count', 'Avg Invoice Value', 'Median Invoice Value', 'Max Invoice Value', 'Avg Line Items', 'Total Value'], null, "A$row");
            $sheet->getStyle("A$row:G$row")->applyFromArray($headerStyle);
            $row++;
            $totalInvoices = 0; $totalValue = 0; $weightedLineItems = 0;
            foreach ($result['rows'] as $i => $r) {
                $label = $r['label'] . (!empty($r['is_other']) ? ' (' . $r['other_count'] . ' items)' : '');
                $sheet->fromArray([$label, $r['invoice_count'], round($r['avg_invoice_value'],2), round($r['median_invoice_value'],2), round($r['max_invoice_value'],2), round($r['avg_line_items'],2), round($r['total_value'],2)], null, "A$row");
                foreach (['C','D','E','G'] as $col) $sheet->getStyle("{$col}$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:G$row")->applyFromArray($altStyle);
                $totalInvoices += $r['invoice_count'];
                $totalValue += $r['total_value'];
                $weightedLineItems += $r['avg_line_items'] * $r['invoice_count'];
                $row++;
            }
            $sheet->fromArray(['Total', $totalInvoices, $totalInvoices > 0 ? round($totalValue/$totalInvoices,2) : 0, '', '', $totalInvoices > 0 ? round($weightedLineItems/$totalInvoices,2) : 0, round($totalValue,2)], null, "A$row");
            $sheet->getStyle("A$row:G$row")->applyFromArray($totalStyle);
            foreach (['A','B','C','D','E','F','G'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'invoice_analysis' && $result['view'] === 'snapshot') {
            $s = $result['summary'];
            $sheet->fromArray(['Metric', 'Value'], null, "A$row");
            $sheet->getStyle("A$row:B$row")->applyFromArray($headerStyle);
            $row++;
            $summaryRows = [
                ['Total Invoices', $s['invoice_count']],
                ['Total Value', round($s['total_value'],2)],
                ['Average Invoice Value', round($s['avg_invoice_value'],2)],
                ['Median Invoice Value', round($s['median_invoice_value'],2)],
                ['Largest Invoice', round($s['max_invoice_value'],2)],
                ['Average Line Items per Invoice', round($s['avg_line_items'],2)],
            ];
            foreach ($summaryRows as $sr) {
                $sheet->fromArray($sr, null, "A$row");
                $row++;
            }
            $row++;
            $sheet->fromArray(['Value Range', 'Invoice Count', 'Total Value', '% of Revenue'], null, "A$row");
            $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
            $row++;
            foreach ($result['distribution'] as $i => $b) {
                $sheet->fromArray([$b['label'], $b['count'], round($b['total_value'],2), $b['pct_of_revenue'] . '%'], null, "A$row");
                $sheet->getStyle("C$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:D$row")->applyFromArray($altStyle);
                $row++;
            }
            foreach (['A','B','C','D'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        } elseif ($result['type'] === 'invoice_analysis' && $result['view'] === 'large_invoices') {
            $sheet->fromArray(['Document Number', 'Customer Name', 'Date', 'Value', 'Line Items', '% of Total Sales'], null, "A$row");
            $sheet->getStyle("A$row:F$row")->applyFromArray($headerStyle);
            $row++;
            $listValueTotal    = 0;
            $listNetSalesTotal = 0;
            $periodTotal       = $result['period_total'] ?? 0;
            foreach ($result['rows'] as $i => $r) {
                $pct = $periodTotal > 0 ? round($r['net_sales'] / $periodTotal * 100, 2) : 0;
                $sheet->fromArray([$r['document_number'], $r['customer_name'], (string) $r['date'], round($r['value'],2), $r['line_items'], $pct . '%'], null, "A$row");
                $sheet->getStyle("D$row")->getNumberFormat()->setFormatCode($numberFormat);
                if ($i % 2 === 1) $sheet->getStyle("A$row:F$row")->applyFromArray($altStyle);
                $listValueTotal    += $r['value'];
                $listNetSalesTotal += $r['net_sales'];
                $row++;
            }
            $pctOfTotal = $periodTotal > 0 ? round($listNetSalesTotal / $periodTotal * 100, 1) : 0;
            $sheet->fromArray(['Total', '', '', round($listValueTotal,2), '', $pctOfTotal . '%'], null, "A$row");
            $sheet->getStyle("A$row:F$row")->applyFromArray($totalStyle);
            $row++;
            foreach (['A','B','C','D','E','F'] as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
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
        $limit     = (int) ($request->top_n ?? 500);

        $selected = $this->decodeSelectedItems($request->selected_items ?? null);

        $q = $query->whereNotNull($dimension)->where($dimension, '!=', '');
        if (!empty($selected)) {
            $q->whereIn($dimension, $selected);
        }

        $rows = $q->selectRaw("`$dimension` as label, {$this->metricExpr($metric)} as value, COUNT(*) as transactions")
            ->groupBy($dimension)->orderByDesc('value')->get();

        $result = $rows;
        // Only cap with an "Others" bucket when the user didn't hand-pick
        // specific items — an explicit selection is shown in full.
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

    // ── Matrix ─────────────────────────────────────────────────

    private function runMatrix($query, $request)
    {
        $dim1      = $request->dimension1 ?? 'zone';
        $dim2      = $request->dimension2 ?? 'product_category';
        $metric    = $request->metric ?? 'net_sales_value';
        $dim1Limit = 500; // rows scroll vertically — can afford many
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

        // Only cap with an "Others" bucket when the user didn't hand-pick
        // specific rows/columns — an explicit selection is shown in full.
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

        // Fold every raw (dim1, dim2) cell into its kept bucket, or into
        // that axis's "Others" bucket if it fell outside the cap.
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
            ->selectRaw("`branch`, `$dim` as product_dim, {$this->metricExpr($metric)} as value")
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
        $currentYear = (int) date('Y', strtotime($request->date_to));
        $metric      = $request->metric ?? 'net_sales_value';

        // Y, Y-1, Y-2, Y-3, Y-4 — need back to Y-4 because "Repeating" checks
        // whether Y-1 was itself that customer's New year (which looks back
        // 3 years from Y-1, i.e. as far as Y-4).
        $years = [];
        for ($i = 0; $i <= 4; $i++) {
            $y = $currentYear - $i;
            $years[$i] = SalesData::where('portfolio_company_id', $companyId)
                ->whereYear('date', $y)->whereNotNull('customer_name')
                ->pluck('customer_name')->unique();
        }
        [$setY, $setY1, $setY2, $setY3, $setY4] = $years;

        $buckets = [
            // Active this year, absent the 3 years before — true first-timers
            // (also correctly catches anyone returning after a 3+ year gap).
            'new'              => $setY->diff($setY1)->diff($setY2)->diff($setY3)->values(),

            // Active this year AND last year, AND last year was itself a
            // "New" year for them (absent the 3 years before that).
            'repeating'        => $setY->intersect($setY1)->diff($setY2)->diff($setY3)->diff($setY4)->values(),

            // Active 3 straight consecutive years: Y, Y-1, Y-2.
            'active'           => $setY->intersect($setY1)->intersect($setY2)->values(),

            'stop'             => $setY1->diff($setY)->values(),
            'dead'             => $setY2->diff($setY1)->diff($setY)->values(),

            // Active Y-2, paused Y-1, back this year (1-year gap).
            'stop_reactivated' => $setY->intersect($setY2)->diff($setY1)->values(),

            // Active Y-3, paused Y-2 AND Y-1, back this year (2-year gap).
            'dead_reactivated' => $setY->intersect($setY3)->diff($setY2)->diff($setY1)->values(),
        ];

        $salesByCustomer = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')
            ->get()
            ->keyBy('customer_name');

        // "Stop" and "Dead" customers have zero sales in the selected
        // period by definition — that's what makes them stopped/dead. To
        // show the user how much revenue is actually being lost, pull
        // their sales from the LAST year they were genuinely active
        // (Y-1 for Stop, Y-2 for Dead) instead of the current period.
        $salesLastYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 1)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $salesTwoYearsAgo = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 2)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw("customer_name, {$this->metricExpr($metric)} as total_sales")
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $pastPeriodSales = ['stop' => $salesLastYear, 'dead' => $salesTwoYearsAgo];
        $pastPeriodYear  = ['stop' => $currentYear - 1, 'dead' => $currentYear - 2];

        $grandTotal = $salesByCustomer->sum('total_sales');

        $categories = collect($buckets)->map(function ($customers, $key) use ($salesByCustomer, $pastPeriodSales, $pastPeriodYear, $grandTotal) {
            $isPastPeriod = isset($pastPeriodSales[$key]);
            $salesMap     = $pastPeriodSales[$key] ?? $salesByCustomer;

            $rows = $customers->map(function ($name) use ($salesMap) {
                return ['name' => $name, 'sales' => (float) ($salesMap[$name]->total_sales ?? 0)];
            })->sortByDesc('sales')->values();

            // For Stop/Dead, "% of total" means % of that category's own
            // total (their share of what the churned group used to bring
            // in) — comparing against the CURRENT period's grand total
            // would be comparing two different periods' money.
            $percentBase = $isPastPeriod ? $rows->sum('sales') : $grandTotal;
            $rows = $rows->map(function ($r) use ($percentBase) {
                $r['percentage'] = $percentBase > 0 ? round($r['sales'] / $percentBase * 100, 2) : 0;
                return $r;
            });

            return [
                'label'            => $key,
                'count'            => $customers->count(),
                'total_sales'      => $rows->sum('sales'),
                'is_past_period'   => $isPastPeriod,
                'sales_period_year'=> $isPastPeriod ? $pastPeriodYear[$key] : null,
                'customers'        => $rows,
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
        $limit  = 500;

        $periods  = $this->decodePeriods($request->periods ?? null, $request);
        $selected = $this->decodeSelectedItems($request->selected_items ?? null);
        $lastIdx  = count($periods) - 1;

        // Pull summed value per label, once per period — always the FULL,
        // unfiltered set. Rank must be computed against every item that
        // period, not just whichever items the user chose to display, or
        // "rank #2" would silently mean different things depending on what
        // happened to be selected.
        $perPeriodData  = [];
        $periodRanks    = [];
        $periodCounts   = [];
        foreach ($periods as $i => $p) {
            $full = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$p['from'], $p['to']])
                ->whereNotNull($dim)->where($dim, '!=', '')
                ->selectRaw("`$dim` as label, {$this->metricExpr($metric)} as value")
                ->groupBy($dim)->get();

            $sorted = $full->sortByDesc('value')->values();
            $rankLookup = [];
            foreach ($sorted as $idx => $row) {
                $rankLookup[$row->label] = $idx + 1;
            }
            $periodRanks[$i]  = $rankLookup;
            $periodCounts[$i] = $sorted->count();

            $displayData = empty($selected) ? $full : $full->whereIn('label', $selected);
            $perPeriodData[$i] = $displayData->keyBy('label');
        }

        $allLabels = collect();
        foreach ($perPeriodData as $data) {
            $allLabels = $allLabels->merge($data->keys());
        }
        $allLabels = $allLabels->unique()->values();

        $rows = $allLabels->map(function ($label) use ($perPeriodData, $periodRanks, $periodCounts, $lastIdx) {
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
            $ranks = [];
            $prevRank = null;
            foreach ($periodRanks as $i => $lookup) {
                $rank = $lookup[$label] ?? null;
                $ranks[$i] = [
                    'rank'        => $rank,
                    'total'       => $periodCounts[$i],
                    // Positive = climbed (rank number went down); negative = slipped.
                    'rank_change' => ($rank !== null && $prevRank !== null) ? $prevRank - $rank : null,
                ];
                $prevRank = $rank;
            }
            return [
                'label'      => $label,
                'values'     => $values,
                'changes'    => $changes,
                'ranks'      => $ranks,
                'sort_value' => $values[$lastIdx], // rank by the LATEST period chosen
            ];
        });

        // Sort largest → smallest by the latest period's value.
        $rows = $rows->sortByDesc('sort_value')->values();

        // Only cap with an "Others" row when nothing was hand-picked.
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
                'ranks'       => null, // "Others" bundles many items — no single rank applies
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
        $limit  = 500;

        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $dim1Selected = $this->decodeSelectedItems($request->dim1_items ?? null);
        $dim2Selected = $this->decodeSelectedItems($request->dim2_items ?? null);

        // Clone BEFORE any item-selection filters are applied, so we still
        // have an unfiltered query to compute TRUE global rank from — rank
        // must reflect the item's real position among everything that
        // period, not just among whichever items happen to be selected
        // for display.
        $rankQuery = clone $query;

        $query->whereNotNull($dim1)->where($dim1, '!=', '')
              ->whereNotNull($dim2)->where($dim2, '!=', '');
        if (!empty($dim1Selected)) $query->whereIn($dim1, $dim1Selected);
        if (!empty($dim2Selected)) $query->whereIn($dim2, $dim2Selected);

        $data = $query
            ->selectRaw("
                `$dim1`     as dim1,
                `$dim2`     as dim2,
                $labelExpr  as period_label,
                $sortExpr   as sort_key,
                {$this->metricExpr($metric)} as value
            ")
            ->groupBy('dim1', 'dim2', 'period_label', 'sort_key')
            ->get();

        $periods = $data->sortBy('sort_key')->pluck('period_label')->unique()->values();

        // True global parent (dim1) rank per period — intentionally
        // includes a dim1's ENTIRE footprint (even rows with a blank
        // dim2), since ranking an item's overall size shouldn't depend on
        // whether every one of its sales happens to also have a valid
        // Factor 2 value.
        $rankData = $rankQuery->whereNotNull($dim1)->where($dim1, '!=', '')
            ->selectRaw("`$dim1` as dim1, $labelExpr as period_label, {$this->metricExpr($metric)} as value")
            ->groupBy('dim1', 'period_label')
            ->get();

        $rankByPeriod = [];
        foreach ($rankData->groupBy('period_label') as $periodLabel => $group) {
            $sorted = $group->sortByDesc('value')->values();
            $lookup = [];
            foreach ($sorted as $idx => $row) {
                $lookup[$row->dim1] = $idx + 1;
            }
            $rankByPeriod[$periodLabel] = ['ranks' => $lookup, 'total' => $sorted->count()];
        }

        // Totals per dim1, used to rank (largest → smallest) and to decide
        // which categories get folded into "Others" (Top 500 overall).
        // Note: dim2 (products) is intentionally NOT capped here — that cap
        // is applied per-category below, since a category with fewer than
        // 500 products should never get an "Others" row at all.
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

        // Same as $buildCells, but also attaches each period's TRUE global
        // rank + rank movement vs the previous period — only meaningful
        // for parent (dim1) rows, never for children or "Others".
        $buildParentCells = function (array $periodValues, string $dim1Label) use ($periods, $buildCells, $rankByPeriod) {
            $cells    = $buildCells($periodValues);
            $prevRank = null;
            foreach ($periods as $p) {
                $lookup = $rankByPeriod[$p]['ranks'] ?? [];
                $rank   = $lookup[$dim1Label] ?? null;
                $cells[$p]['rank']        = $rank;
                $cells[$p]['rank_total']  = $rankByPeriod[$p]['total'] ?? null;
                $cells[$p]['rank_change'] = ($rank !== null && $prevRank !== null) ? $prevRank - $rank : null;
                $prevRank = $rank;
            }
            return $cells;
        };

        // $dim1Keys is already ordered largest → smallest (arsort above),
        // so iterating it in order gives us correctly-sorted parent rows.
        $resultRows = [];
        foreach ($dim1Keys as $d1) {
            $dim2Groups = $grouped[$d1] ?? [];

            // Rank & cap Factor 2 items WITHIN this category only — the
            // 500 limit applies per category, never to the report overall.
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
            // Sort child rows largest → smallest within this parent.
            usort($childRows, fn($a, $b) => $b['total'] <=> $a['total']);

            if (!empty($otherChildTotals)) {
                $cells = $buildCells($otherChildTotals);
                $childRows[] = [
                    'label'       => 'Others',
                    'cells'       => $cells,
                    'total'       => array_sum(array_column($cells, 'value')),
                    'is_other'    => true,
                    'other_count' => $otherChildCount, // items excluded WITHIN this specific category
                ];
            }

            $parentPeriodTotals = [];
            foreach ($dim2Groups as $periodData) {
                foreach ($periodData as $p => $v) {
                    $parentPeriodTotals[$p] = ($parentPeriodTotals[$p] ?? 0) + $v;
                }
            }
            $parentCells = $buildParentCells($parentPeriodTotals, $d1);

            $resultRows[] = [
                'label'    => $d1,
                'cells'    => $parentCells,
                'total'    => array_sum(array_column($parentCells, 'value')),
                'children' => $childRows,
            ];
        }

        // A single "Others" parent row combining every dim1 item beyond Top 500.
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
            ->selectRaw("$labelExpr as period_label, $sortExpr as sort_key, {$this->metricExpr($metric)} as value")
            ->groupBy('period_label', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return $raw->map(fn($r) => [
            'period' => $r->period_label,
            'value'  => (float) $r->value,
        ])->values()->toArray();
    }

    // ── Report Types ───────────────────────────────────────────

    // ── Invoice Analysis ─────────────────────────────────────────

    private function median(\Illuminate\Support\Collection $sortedValues): float
    {
        $n = $sortedValues->count();
        if ($n === 0) return 0;
        $mid = intdiv($n, 2);
        if ($n % 2 === 1) return (float) $sortedValues[$mid];
        return (float) (($sortedValues[$mid - 1] + $sortedValues[$mid]) / 2);
    }

    private function runInvoiceAnalysis($companyId, $request)
    {
        return match($request->invoice_view ?? 'snapshot') {
            'by_dimension'   => $this->invoiceByDimension($companyId, $request),
            'large_invoices' => $this->invoiceLargeList($companyId, $request),
            default          => $this->invoiceSnapshot($companyId, $request),
        };
    }

    // View A — breakdown by dimension. Note: for a dimension that can span
    // multiple lines per invoice (e.g. Product Category), "invoice" here
    // means "the portion of that invoice touching this dimension value" —
    // the standard way analysts handle this (like "average order value for
    // orders containing X"), not the whole basket. For invoice-level
    // dimensions (Customer, Sales Person) it's exactly the real invoice.
    private function invoiceByDimension($companyId, $request)
    {
        $dim    = $request->dimension1 ?? 'customer_name';
        $metric = $request->metric ?? 'net_sales_value';
        $limit  = 500;

        $selected = $this->decodeSelectedItems($request->dim1_items ?? null);

        $q = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->whereNotNull($dim)->where($dim, '!=', '');
        if (!empty($selected)) {
            $q->whereIn($dim, $selected);
        }

        // One row per (invoice, dimension value) — the smallest granularity
        // that still lets us compute a real median per group.
        $perInvoice = $q->selectRaw("document_number, `$dim` as dim_value, {$this->metricExpr($metric)} as value, COUNT(*) as line_items")
            ->groupBy('document_number', $dim)
            ->get();

        $grouped = $perInvoice->groupBy('dim_value');

        $rows = $grouped->map(function ($invoices, $dimValue) {
            $values = $invoices->pluck('value')->map(fn($v) => (float) $v)->sort()->values();
            return [
                'label'                => $dimValue,
                'invoice_count'        => $invoices->count(),
                'total_value'          => $values->sum(),
                'avg_invoice_value'    => $invoices->count() > 0 ? $values->sum() / $invoices->count() : 0,
                'median_invoice_value' => $this->median($values),
                'max_invoice_value'    => $values->max() ?? 0,
                'avg_line_items'       => $invoices->count() > 0 ? $invoices->sum('line_items') / $invoices->count() : 0,
                '_raw_values'          => $values, // used only to build the Others bucket below, stripped before returning
            ];
        })->sortByDesc('total_value')->values();

        if (empty($selected) && $rows->count() > $limit) {
            $top  = $rows->take($limit);
            $rest = $rows->slice($limit);

            $otherValues = $rest->flatMap(fn($r) => $r['_raw_values'])->sort()->values();
            $otherInvoiceCount = $rest->sum('invoice_count');
            $otherLineItems    = $rest->reduce(fn($carry, $r) => $carry + ($r['avg_line_items'] * $r['invoice_count']), 0);

            $rows = $top->values();
            $rows->push([
                'label'                => 'Others',
                'invoice_count'        => $otherInvoiceCount,
                'total_value'          => $otherValues->sum(),
                'avg_invoice_value'    => $otherInvoiceCount > 0 ? $otherValues->sum() / $otherInvoiceCount : 0,
                'median_invoice_value' => $this->median($otherValues),
                'max_invoice_value'    => $otherValues->max() ?? 0,
                'avg_line_items'       => $otherInvoiceCount > 0 ? $otherLineItems / $otherInvoiceCount : 0,
                'is_other'             => true,
                'other_count'          => $rest->count(),
            ]);
        }

        // Strip the internal-only raw values before returning to the client.
        $rows = $rows->map(function ($r) {
            unset($r['_raw_values']);
            return $r;
        })->values();

        return [
            'type'      => 'invoice_analysis',
            'view'      => 'by_dimension',
            'dimension' => $dim,
            'metric'    => $metric,
            'rows'      => $rows,
        ];
    }

    // View B — company-wide invoice snapshot + value-bucket distribution.
    private function invoiceSnapshot($companyId, $request)
    {
        $metric = $request->metric ?? 'net_sales_value';

        $invoices = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->selectRaw("document_number, {$this->metricExpr($metric)} as value, COUNT(*) as line_items")
            ->groupBy('document_number')
            ->get();

        $values         = $invoices->pluck('value')->map(fn($v) => (float) $v)->sort()->values();
        $count          = $values->count();
        $totalValue     = $values->sum();
        $totalLineItems = $invoices->sum('line_items');

        $buckets = [
            ['label' => 'Under 10K',   'min' => -INF, 'max' => 10000],
            ['label' => '10K – 50K',   'min' => 10000, 'max' => 50000],
            ['label' => '50K – 100K',  'min' => 50000, 'max' => 100000],
            ['label' => '100K – 250K', 'min' => 100000, 'max' => 250000],
            ['label' => '250K – 500K', 'min' => 250000, 'max' => 500000],
            ['label' => '500K – 750K', 'min' => 500000, 'max' => 750000],
            ['label' => '750K – 1000K','min' => 750000, 'max' => 1000000],
            ['label' => 'Over 1M',     'min' => 1000000, 'max' => INF],
        ];
        $distribution = collect($buckets)->map(function ($b) use ($values, $totalValue) {
            $matched = $values->filter(fn($v) => $v >= $b['min'] && $v < $b['max']);
            return [
                'label'          => $b['label'],
                'count'          => $matched->count(),
                'total_value'    => $matched->sum(),
                'pct_of_revenue' => $totalValue > 0 ? round($matched->sum() / $totalValue * 100, 1) : 0,
            ];
        })->values();

        return [
            'type'    => 'invoice_analysis',
            'view'    => 'snapshot',
            'metric'  => $metric,
            'summary' => [
                'invoice_count'        => $count,
                'total_value'          => $totalValue,
                'avg_invoice_value'    => $count > 0 ? $totalValue / $count : 0,
                'median_invoice_value' => $this->median($values),
                'max_invoice_value'    => $values->max() ?? 0,
                'avg_line_items'       => $count > 0 ? $totalLineItems / $count : 0,
            ],
            'distribution' => $distribution,
        ];
    }

    // View C — drill-down list of individual large invoices above a
    // user-chosen threshold, for one-off/anomaly checking.
    private function invoiceLargeList($companyId, $request)
    {
        $metric    = $request->metric ?? 'net_sales_value';
        $threshold = (float) ($request->invoice_threshold ?? 1000000);
        $limit     = 500;

        $invoices = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->selectRaw("document_number, customer_name, MIN(date) as invoice_date, {$this->metricExpr($metric)} as value, SUM(net_sales_value) as net_sales, COUNT(*) as line_items")
            ->groupBy('document_number', 'customer_name')
            ->havingRaw("{$this->metricExpr($metric)} >= ?", [$threshold])
            ->orderByDesc('value')
            ->limit($limit)
            ->get();

        // "% of Total Sales" always anchors to real Net Sales Value — a
        // genuinely additive figure that sums to a meaningful company
        // total — regardless of which metric the list is sorted/filtered
        // by. A metric like Price Per Unit is itself an average, not
        // something with a sensible "company total" to be a % of; using
        // it as the denominator here produced nonsense percentages.
        $periodTotal = (float) (SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->sum('net_sales_value'));

        return [
            'type'         => 'invoice_analysis',
            'view'         => 'large_invoices',
            'metric'       => $metric,
            'threshold'    => $threshold,
            'truncated'    => $invoices->count() >= $limit,
            'period_total' => $periodTotal,
            'rows'         => $invoices->map(fn($r) => [
                'document_number' => $r->document_number,
                'customer_name'   => $r->customer_name,
                'date'            => $r->invoice_date,
                'value'           => (float) $r->value,
                'net_sales'       => (float) $r->net_sales,
                'line_items'      => (int) $r->line_items,
            ])->values(),
        ];
    }

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
            ['key' => 'invoice_analysis',  'label' => 'Invoice Analysis',    'description' => 'Average invoice value, line items per invoice, and large-invoice detection'],
        ];
    }
}