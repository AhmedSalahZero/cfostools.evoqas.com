<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExpenseReportExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected string $companyName;
    protected string $reportType;
    protected string $dateFrom;
    protected string $dateTo;
    protected array  $data;
    protected float  $totalExpense;
    protected float  $totalRevenue;

    public function __construct(
        string $companyName,
        string $reportType,
        string $dateFrom,
        string $dateTo,
        array  $data,
        float  $totalExpense,
        float  $totalRevenue
    ) {
        $this->companyName  = $companyName;
        $this->reportType   = $reportType;
        $this->dateFrom     = $dateFrom;
        $this->dateTo       = $dateTo;
        $this->data         = $data;
        $this->totalExpense = $totalExpense;
        $this->totalRevenue = $totalRevenue;
    }

    public function title(): string
    {
        $labels = [
            'category_breakdown'    => 'Category Breakdown',
            'subcategory_breakdown' => 'Sub-Category Breakdown',
            'item_breakdown'        => 'Item Breakdown',
            'min_avg_max'           => 'Min Avg Max',
            'period_comparison'     => 'Period Comparison',
        ];
        return $labels[$this->reportType] ?? 'Report';
    }

    public function headings(): array
    {
        return match($this->reportType) {
            'category_breakdown' => ['Expense Category', 'Total Amount', '% of Total Expense', '% of Revenue'],
            'subcategory_breakdown' => ['Expense Category', 'Sub Category', 'Total Amount', '% of Total Expense', '% of Revenue'],
            'item_breakdown' => ['Expense Category', 'Expense Item', 'Total Amount', '% of Total Expense', '% of Revenue'],
            'min_avg_max'    => ['Expense Category', 'Expense Item', 'Months', 'Min (Monthly)', 'Avg (Monthly)', 'Max (Monthly)', 'Std Dev', 'Outlier Months'],
            'period_comparison' => $this->periodComparisonHeadings(),
            default => [],
        };
    }

    // period_comparison's $data is the full result array (periods + rows),
    // not a flat row list like the other report types, since it needs a
    // variable number of period columns (2–5).
    private function periodComparisonHeadings(): array
    {
        $periods = $this->data['periods'] ?? [];
        $heads   = ['Label'];
        foreach ($periods as $i => $p) {
            $heads[] = 'Period ' . ($i + 1) . " ({$p['from']} to {$p['to']})";
            if ($i > 0) $heads[] = "Change % (vs Period {$i})";
        }
        return $heads;
    }

    private function periodComparisonRows(): array
    {
        $rows = [];
        foreach (($this->data['rows'] ?? []) as $r) {
            $row = [$r['label']];
            foreach (($r['values'] ?? []) as $i => $v) {
                $row[] = $v;
                if ($i > 0) {
                    $change = $r['changes'][$i - 1] ?? null;
                    $row[]  = $change === null ? 'N/A' : $change . '%';
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function array(): array
    {
        return match($this->reportType) {
            'category_breakdown' => array_map(fn($r) => [
                $r['category'],
                $r['total'],
                $r['pct_of_expense'] . '%',
                $r['pct_of_revenue'] . '%',
            ], $this->data),

            'subcategory_breakdown' => array_map(fn($r) => [
                $r['category'],
                $r['sub_category'],
                $r['total'],
                $r['pct_of_expense'] . '%',
                $r['pct_of_revenue'] . '%',
            ], $this->data),

            'item_breakdown' => array_map(fn($r) => [
                $r['category'],
                $r['item'],
                $r['total'],
                $r['pct_of_expense'] . '%',
                $r['pct_of_revenue'] . '%',
            ], $this->data),

            'min_avg_max' => array_map(fn($r) => [
                $r['category'],
                $r['item'],
                $r['months_count'],
                $r['min'],
                $r['avg'],
                $r['max'],
                $r['std_dev'],
                $r['outlier_count'],
            ], $this->data),

            'period_comparison' => $this->periodComparisonRows(),

            default => [],
        };
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 28, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18, 'G' => 14, 'H' => 14];
    }
}