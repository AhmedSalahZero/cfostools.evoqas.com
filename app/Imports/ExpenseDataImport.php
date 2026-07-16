<?php

namespace App\Imports;

use App\Models\ExpenseData;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExpenseDataImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int    $companyId;
    protected int    $uploadId;
    protected string $dateFormat;
    protected int    $rowCount  = 0;
    protected int    $maxRows   = 50000;

    public function __construct(int $companyId, int $uploadId, string $dateFormat = 'DD/MM/YYYY')
    {
        $this->companyId  = $companyId;
        $this->uploadId   = $uploadId;
        $this->dateFormat = $dateFormat;
    }

    public function collection(Collection $rows)
    {
        if ($this->rowCount >= $this->maxRows) return;

        $batch = [];

        // Column name → normalized map
        // We support flexible header naming (case insensitive, spaces/underscores)
        $columnAliases = [
            'date'                 => ['date', 'expense_date', 'trans_date', 'transaction_date'],
            'expense_category'     => ['expense_category', 'category', 'expense_cat', 'expensecategory'],
            'expense_sub_category' => ['expense_sub_category', 'sub_category', 'subcategory', 'expense_subcategory', 'sub category'],
            'expense_name'         => ['expense_name', 'expensename', 'expense_item', 'item_name', 'item', 'name'],
            'expense_amount'       => ['expense_amount', 'amount', 'expenseamount', 'value', 'expense_value'],
        ];

        foreach ($rows as $row) {
            if ($this->rowCount >= $this->maxRows) break;

            $rowArray = $row->toArray();
            if ($this->isEmptyRow($rowArray)) continue;

            // Normalize all column headers in this row
            $normalized = [];
            foreach ($rowArray as $colName => $colValue) {
                $normKey = Str::lower(preg_replace('/[\s_]+/', '_', trim((string) $colName)));
                $normalized[$normKey] = $colValue;
            }

            $record = [
                'portfolio_company_id' => $this->companyId,
                'upload_id'            => $this->uploadId,
                'created_at'           => now(),
                'updated_at'           => now(),
            ];

            // Map each field using alias list
            foreach ($columnAliases as $fieldKey => $aliases) {
                $value = null;
                foreach ($aliases as $alias) {
                    $aliasNorm = Str::lower(preg_replace('/[\s_]+/', '_', $alias));
                    if (array_key_exists($aliasNorm, $normalized)) {
                        $value = $normalized[$aliasNorm];
                        break;
                    }
                }
                $record[$fieldKey] = $this->castValue($fieldKey, $value);
            }

            $batch[] = $record;
            $this->rowCount++;

            if (count($batch) >= 2000) {
                ExpenseData::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            ExpenseData::insert($batch);
        }
    }

    private function castValue(string $field, $value)
    {
        if ($value === null || $value === '') return null;

        if ($field === 'expense_amount') {
            $clean = str_replace(',', '', (string) $value);
            return is_numeric($clean) ? (float) $clean : null;
        }

        if ($field === 'date') {
            return $this->parseDate($value);
        }

        return trim((string) $value);
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;

        try {
            if (is_numeric($value) && $value > 1000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            }

            $str = trim((string) $value);

            $carbonFormat = match($this->dateFormat) {
                'DD/MM/YYYY' => 'd/m/Y',
                'MM/DD/YYYY' => 'm/d/Y',
                'YYYY/MM/DD' => 'Y/m/d',
                'DD-MM-YYYY' => 'd-m-Y',
                'MM-DD-YYYY' => 'm-d-Y',
                'YYYY-MM-DD' => 'Y-m-d',
                default      => 'd/m/Y',
            };

            return Carbon::createFromFormat($carbonFormat, $str)->format('Y-m-d');

        } catch (\Exception $e) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn($v) => $v !== null && $v !== ''));
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function hitRowLimit(): bool
    {
        return $this->rowCount >= $this->maxRows;
    }
}