<?php

namespace App\Imports;

use App\Models\SalesData;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SalesDataImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $companyId;
    protected int $uploadId;
    protected array $activeFields;
    protected array $fieldLabels;
    protected string $dateFormat;
    protected int $rowCount = 0;
    protected int $maxRows = 50000;

    // NEW: Fast lookup map (normalized header → fieldKey)
    protected array $normalizedFieldMap = [];

    public function __construct(
        int $companyId,
        int $uploadId,
        array $activeFields,
        array $fieldLabels,
        string $dateFormat = 'DD/MM/YYYY'
    ) {
        $this->companyId    = $companyId;
        $this->uploadId     = $uploadId;
        $this->activeFields = $activeFields;
        $this->fieldLabels  = $fieldLabels;
        $this->dateFormat   = $dateFormat;

        // Pre-build fast lookup (done only once)
        foreach ($this->activeFields as $fieldKey) {
            $label = $this->fieldLabels[$fieldKey] ?? $fieldKey;
            $norm  = Str::lower(preg_replace('/\s+/', '_', trim($label)));
            $this->normalizedFieldMap[$norm] = $fieldKey;
        }
    }

    public function collection(Collection $rows)
    {
        if ($this->rowCount >= $this->maxRows) return;

        $batch = [];

        foreach ($rows as $row) {
            if ($this->rowCount >= $this->maxRows) break;

            $rowArray = $row->toArray();
            if ($this->isEmptyRow($rowArray)) continue;

            // Normalize row headers ONCE (exactly like ExpenseDataImport)
            $normalizedRow = [];
            foreach ($rowArray as $colName => $colValue) {
                $colNorm = Str::lower(preg_replace('/\s+/', '_', trim((string)$colName)));
                $normalizedRow[$colNorm] = $colValue;
            }

            $record = [
                'portfolio_company_id' => $this->companyId,
                'upload_id'            => $this->uploadId,
                'created_at'           => now(),
                'updated_at'           => now(),
            ];

            // Super fast O(1) lookup for every field
            foreach ($this->normalizedFieldMap as $normHeader => $fieldKey) {
                $value = $normalizedRow[$normHeader] ?? null;
                $record[$fieldKey] = $this->castValue($fieldKey, $value);
            }

            $batch[] = $record;
            $this->rowCount++;

            if (count($batch) >= 2000) {
                SalesData::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            SalesData::insert($batch);
        }
    }

    // castValue, parseDate, isEmptyRow stay exactly the same as you had
    private function castValue(string $fieldKey, $value)
    {
        if ($value === null || $value === '') return null;

        $numericFields = [
            'quantity', 'sales_value', 'cash_discount',
            'quantity_discount', 'special_discount',
            'other_discounts', 'net_sales_value', 'price_per_unit',
        ];

        if (in_array($fieldKey, $numericFields)) {
            $clean = str_replace(',', '', (string)$value);
            return is_numeric($clean) ? (float)$clean : null;
        }

        if ($fieldKey === 'service_provider_birth_year') {
            return is_numeric($value) ? (int)$value : null;
        }

        if ($fieldKey === 'date') {
            return $this->parseDate($value);
        }

        return trim((string)$value);
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;

        try {
            if (is_numeric($value) && $value > 1000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            }

            $str = trim((string)$value);

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
        return 2000;   // same as expense → faster & consistent
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