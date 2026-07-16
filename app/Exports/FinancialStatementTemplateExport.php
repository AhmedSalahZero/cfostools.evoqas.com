<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialStatementTemplateExport implements WithMultipleSheets
{
    public function __construct(
        protected $sections,
        protected $company,
    ) {}

    public function sheets(): array
    {
        return [
            new FsTemplateSheetExport($this->sections, 'income',        'Income Statement', $this->company),
            new FsTemplateSheetExport($this->sections, 'balance_sheet', 'Balance Sheet',    $this->company),
            new FsTemplateSheetExport($this->sections, 'cashflow',      'Cash Flow',        $this->company),
        ];
    }
}


class FsTemplateSheetExport implements
    \Maatwebsite\Excel\Concerns\FromArray,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithStyles,
    \Maatwebsite\Excel\Concerns\WithColumnWidths
{
    private array $boldRows    = [];
    private array $sectionRows = [];
    private array $computedRows = [];
    private array $lockedRows  = [];   // rows user should NOT edit
    private int   $currentRow  = 1;
    private array $rows        = [];

    public function __construct(
        protected $sections,
        protected string $type,
        protected string $sheetName,
        protected $company,
    ) {}

    public function title(): string { return $this->sheetName; }

    public function array(): array
    {
        $this->rows        = [];
        $this->boldRows    = [];
        $this->sectionRows = [];
        $this->computedRows = [];
        $this->lockedRows  = [];
        $this->currentRow  = 1;

        $currency = $this->company->invested_currency ?? 'USD';

        // ── Instructions row ──
        $this->addRow(['⚠ DO NOT change Description labels. Only fill in the Amount column (column B).'], instruction: true);
        $this->addRow(['⚠ DO NOT add or remove rows. Keep sheet names exactly as they are.'], instruction: true);
        $this->addRow([]);

        // ── Column headers ──
        $this->addRow(['Description', 'Amount (' . $currency . ')'], bold: true, header: true);

        // ── Sections ──
        $typeSections = $this->sections
            ->where('statement_type', $this->type)
            ->sortBy('sort_order');

        foreach ($typeSections as $sec) {
            if ($sec->is_computed) {
                // Computed rows: show label, leave amount blank, mark as locked
                $this->addRow([$sec->display_name, ''], bold: true, computed: true);
            } else {
                // Section header row (no amount — just a label)
                $this->addRow([$sec->display_name, ''], bold: true, section: true);

                // Line item rows — these are the ones the user fills in
                foreach ($sec->lineItems->sortBy('sort_order') as $li) {
                    $this->addRow(['    ' . $li->label, 0], editable: true);
                }
            }
        }

        return $this->rows;
    }

    private function addRow(
        array $data,
        bool $bold        = false,
        bool $header      = false,
        bool $section     = false,
        bool $computed    = false,
        bool $instruction = false,
        bool $editable    = false,
    ): void {
        $this->rows[] = $data;
        if ($bold || $header || $section)  $this->boldRows[]    = $this->currentRow;
        if ($section)                      $this->sectionRows[]  = $this->currentRow;
        if ($computed)                     $this->computedRows[] = $this->currentRow;
        if ($instruction || !$editable && !$header) $this->lockedRows[] = $this->currentRow;
        $this->currentRow++;
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $lastRow = $this->currentRow - 1;

        // ── Bold rows ──
        foreach ($this->boldRows as $r) {
            $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
        }

        // ── Header row (row 4) ──
        $sheet->getStyle('A4:B4')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $sheet->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('B4')->getAlignment()->setHorizontal('right');

        // ── Instruction rows (rows 1-2) ──
        $sheet->getStyle('A1:B2')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEF3C7');
        $sheet->getStyle('A1:B2')->getFont()->getColor()->setRGB('92400E');
        $sheet->getStyle('A1:B2')->getFont()->setItalic(true);

        // ── Section header rows (dark gray) ──
        foreach ($this->sectionRows as $r) {
            $sheet->getStyle("A{$r}:B{$r}")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('374151');
            $sheet->getStyle("A{$r}:B{$r}")->getFont()->getColor()->setRGB('F9FAFB');
        }

        // ── Computed rows (blue tint, no input expected) ──
        foreach ($this->computedRows as $r) {
            $sheet->getStyle("A{$r}:B{$r}")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DBEAFE');
            $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true)->getColor()->setRGB('1E40AF');
            // Add "(auto-calculated)" note
            $existing = $sheet->getCell("A{$r}")->getValue();
            $sheet->setCellValue("A{$r}", $existing . '  [AUTO — do not fill]');
        }

        // ── Editable rows: green highlight on column B to guide user ──
        for ($r = 5; $r <= $lastRow; $r++) {
            if (!in_array($r, $this->sectionRows) && !in_array($r, $this->computedRows)) {
                $sheet->getStyle("B{$r}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F0FDF4');
                $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal('right');
                // Number format
                $sheet->getStyle("B{$r}")->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }

        // ── Freeze top 4 rows ──
        $sheet->freezePane('A5');

        // ── Border around data area ──
        $sheet->getStyle("A4:B{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('D1D5DB');

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 48, 'B' => 20];
    }
}