<?php

namespace App\Exports;

use App\Models\FinancialStatement;
use App\Models\FsSection;
use App\Models\FsRatio;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialStatementExport implements WithMultipleSheets
{
    public function __construct(protected FinancialStatement $statement) {}

    public function sheets(): array
    {
        $sections = FsSection::where('financial_statement_id', $this->statement->id)
            ->with('lineItems')
            ->orderBy('sort_order')
            ->get();

        $ratios = FsRatio::where('financial_statement_id', $this->statement->id)
            ->orderBy('ratio_group')
            ->get();

        return [
            new FinancialStatementSheetExport($this->statement, $sections, 'income',        'Income Statement'),
            new FinancialStatementSheetExport($this->statement, $sections, 'balance_sheet', 'Balance Sheet'),
            new FinancialStatementSheetExport($this->statement, $sections, 'cashflow',      'Cash Flow'),
            new FinancialRatiosSheetExport($this->statement, $ratios),
        ];
    }
}


// ─────────────────────────────────────────────────────────────
// SHEET: One statement type (income / balance_sheet / cashflow)
// ─────────────────────────────────────────────────────────────
class FinancialStatementSheetExport implements
    \Maatwebsite\Excel\Concerns\FromArray,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithStyles,
    \Maatwebsite\Excel\Concerns\WithColumnWidths,
    \Maatwebsite\Excel\Concerns\ShouldAutoSize
{
    private array $rows = [];
    private array $boldRows = [];
    private array $computedRows = [];
    private int   $currentRow = 1;

    public function __construct(
        protected FinancialStatement $statement,
        protected $sections,
        protected string $type,
        protected string $sheetName,
    ) {}

    public function title(): string { return $this->sheetName; }

    public function array(): array
    {
        $this->rows       = [];
        $this->boldRows   = [];
        $this->computedRows = [];
        $this->currentRow = 1;

        $currency = $this->statement->currency;

        // ── Title rows ──
        $this->addRow([$this->sheetName], bold: true);
        $this->addRow([$this->statement->portfolio_company_id
            ? \App\Models\PortfolioCompany::find($this->statement->portfolio_company_id)?->name ?? ''
            : '']);
        $periodFrom = \Carbon\Carbon::parse($this->statement->period_from)->format('d M Y');
        $periodTo   = \Carbon\Carbon::parse($this->statement->period_to)->format('d M Y');
        $this->addRow(["Period: {$periodFrom} — {$periodTo}"]);
        $this->addRow(['Currency: ' . $currency]);
        $this->addRow([]); // spacer

        // ── Column headers ──
        $this->addRow(['Description', 'Amount (' . $currency . ')', 'Common-Size %'], bold: true, header: true);

        // Figure out common-size base
        $allSections = $this->sections->where('financial_statement_id', $this->statement->id);
        $totalsMap   = $this->buildTotals($allSections);
        $base = match($this->type) {
            'income'        => $totalsMap['sales_revenue'] ?? 0,
            'balance_sheet' => $totalsMap['total_assets']  ?? 0,
            default         => 0,
        };

        // ── Sections ──
        $typeSections = $this->sections->where('statement_type', $this->type)->sortBy('sort_order');

        foreach ($typeSections as $sec) {
            $total     = $totalsMap[$sec->section_key] ?? 0;
            $commonPct = ($base != 0) ? round(($total / $base) * 100, 2) . '%' : '—';

            if ($sec->is_computed) {
                $this->addRow([$sec->display_name, $total, $commonPct], bold: true, computed: true);
            } else {
                $this->addRow([$sec->display_name, $total, $commonPct], bold: true);
                foreach ($sec->lineItems->sortBy('sort_order') as $li) {
                    $this->addRow(['    ' . $li->label, (float) $li->amount, '']);
                }
            }
        }

        return $this->rows;
    }

    private function addRow(array $data, bool $bold = false, bool $computed = false, bool $header = false): void
    {
        $this->rows[] = $data;
        if ($bold || $header) $this->boldRows[] = $this->currentRow;
        if ($computed)        $this->computedRows[] = $this->currentRow;
        $this->currentRow++;
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $styles = [];

        // Title row
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('1E3A5F');

        // Bold rows
        foreach ($this->boldRows as $r) {
            $sheet->getStyle("A{$r}:C{$r}")->getFont()->setBold(true);
            $sheet->getStyle("B{$r}:C{$r}")->getAlignment()->setHorizontal('right');
        }

        // Header row (row 6)
        $sheet->getStyle('A6:C6')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $sheet->getStyle('A6:C6')->getFont()->getColor()->setRGB('FFFFFF');

        // Computed (auto) rows — light blue background
        foreach ($this->computedRows as $r) {
            $sheet->getStyle("A{$r}:C{$r}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DBEAFE');
            $sheet->getStyle("A{$r}:C{$r}")->getFont()->getColor()->setRGB('1E40AF');
        }

        // Number format for column B
        $sheet->getStyle('B7:B1000')->getNumberFormat()->setFormatCode('#,##0.00');

        // Alternating row shading — light gray on non-special rows
        $lastRow = $this->currentRow - 1;
        for ($r = 7; $r <= $lastRow; $r++) {
            if (!in_array($r, $this->computedRows)) {
                $shade = ($r % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                $sheet->getStyle("A{$r}:C{$r}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($shade);
            }
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return ['A' => 45, 'B' => 20, 'C' => 15];
    }

    private function buildTotals($sections): array
    {
        $totals = [];

        foreach ($sections as $sec) {
            if (!$sec->is_computed) {
                $totals[$sec->section_key] = $sec->lineItems->sum('amount');
            }
        }

        for ($pass = 0; $pass < 5; $pass++) {
            foreach ($sections as $sec) {
                if ($sec->is_computed && $sec->computed_from) {
                    $formula = is_string($sec->computed_from)
                        ? json_decode($sec->computed_from, true)
                        : $sec->computed_from;
                    if (!$formula) continue;
                    $result = 0;
                    $allResolved = true;
                    foreach ($formula as $part) {
                        if (!isset($totals[$part['key']])) { $allResolved = false; break; }
                        $result += $totals[$part['key']] * $part['sign'];
                    }
                    if ($allResolved) $totals[$sec->section_key] = $result;
                }
            }
        }

        return $totals;
    }
}


// ─────────────────────────────────────────────────────────────
// SHEET: Financial Ratios
// ─────────────────────────────────────────────────────────────
class FinancialRatiosSheetExport implements
    \Maatwebsite\Excel\Concerns\FromArray,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithStyles,
    \Maatwebsite\Excel\Concerns\WithColumnWidths,
    \Maatwebsite\Excel\Concerns\ShouldAutoSize
{
    private array $boldRows   = [];
    private array $groupRows  = [];
    private int   $currentRow = 1;
    private array $rows       = [];

    public function __construct(
        protected FinancialStatement $statement,
        protected $ratios,
    ) {}

    public function title(): string { return 'Financial Ratios'; }

    public function array(): array
    {
        $this->rows       = [];
        $this->boldRows   = [];
        $this->groupRows  = [];
        $this->currentRow = 1;

        $this->addRow(['Financial Ratios Analysis'], bold: true);
        $this->addRow([]);
        $this->addRow(['Ratio', 'Value', 'Formula'], bold: true, header: true);

        $groupColors = [
            'profitability' => '166534',
            'liquidity'     => '1E40AF',
            'leverage'      => '92400E',
            'activity'      => '6B21A8',
        ];

        $currentGroup = null;
        foreach ($this->ratios as $ratio) {
            if ($ratio->ratio_group !== $currentGroup) {
                $currentGroup = $ratio->ratio_group;
                $this->addRow([strtoupper($currentGroup)], bold: true, groupHeader: true);
            }

            $value = $ratio->ratio_value !== null
                ? $this->formatRatio($ratio->ratio_key, (float) $ratio->ratio_value)
                : '—';

            $this->addRow([
                $ratio->ratio_label,
                $value,
                $this->ratioFormula($ratio->ratio_key),
            ]);
        }

        return $this->rows;
    }

    private function addRow(array $data, bool $bold = false, bool $header = false, bool $groupHeader = false): void
    {
        $this->rows[] = $data;
        if ($bold || $header) $this->boldRows[] = $this->currentRow;
        if ($groupHeader)     $this->groupRows[] = $this->currentRow;
        $this->currentRow++;
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        // Title
        $sheet->getStyle('A1')->getFont()->setSize(13)->setBold(true)->getColor()->setRGB('1E3A5F');

        // Header row
        $sheet->getStyle('A3:C3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $sheet->getStyle('A3:C3')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        // Group headers
        foreach ($this->groupRows as $r) {
            $sheet->getStyle("A{$r}:C{$r}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E5E7EB');
            $sheet->getStyle("A{$r}:C{$r}")->getFont()->setBold(true)->getColor()->setRGB('374151');
        }

        // Bold rows
        foreach ($this->boldRows as $r) {
            $sheet->getStyle("A{$r}:C{$r}")->getFont()->setBold(true);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 35, 'B' => 15, 'C' => 40];
    }

    private function formatRatio(string $key, float $value): string
    {
        $pctKeys = ['gross_margin_pct','ebitda_margin_pct','net_margin_pct','roa','roe','debt_to_assets'];
        return in_array($key, $pctKeys) ? number_format($value, 2) . '%' : number_format($value, 2) . 'x';
    }

    private function ratioFormula(string $key): string
    {
        $map = [
            'gross_margin_pct'    => 'Gross Profit ÷ Revenue',
            'ebitda_margin_pct'   => 'EBITDA ÷ Revenue',
            'net_margin_pct'      => 'Net Profit ÷ Revenue',
            'roa'                 => 'Net Profit ÷ Total Assets',
            'roe'                 => 'Net Profit ÷ Total Equity',
            'current_ratio'       => 'Current Assets ÷ Current Liabilities',
            'quick_ratio'         => 'Liquid Assets ÷ Current Liabilities',
            'debt_to_equity'      => 'Total Liabilities ÷ Total Equity',
            'debt_to_assets'      => 'Total Liabilities ÷ Total Assets',
            'interest_coverage'   => 'EBIT ÷ Interest Expense',
            'asset_turnover'      => 'Revenue ÷ Total Assets',
            'receivables_turnover'=> 'Revenue ÷ Accounts Receivable',
            'inventory_turnover'  => 'COGS ÷ Inventory',
        ];
        return $map[$key] ?? '';
    }
}