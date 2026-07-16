<?php

namespace App\Imports;

use App\Models\ExportSalesData;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExportSalesDataImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $companyId;
    protected int $uploadId;
    protected array $activeFields;
    protected array $fieldLabels;
    protected string $dateFormat;
    protected int $rowCount = 0;
    protected int $maxRows  = 50000;

    protected array $normalizedFieldMap = [];

    public function __construct(
        int    $companyId,
        int    $uploadId,
        array  $activeFields,
        array  $fieldLabels,
        string $dateFormat = 'DD/MM/YYYY'
    ) {
        $this->companyId    = $companyId;
        $this->uploadId     = $uploadId;
        $this->activeFields = $activeFields;
        $this->fieldLabels  = $fieldLabels;
        $this->dateFormat   = $dateFormat;

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

            foreach ($this->normalizedFieldMap as $normHeader => $fieldKey) {
                $value = $normalizedRow[$normHeader] ?? null;
                $record[$fieldKey] = $this->castValue($fieldKey, $value);
            }

            $batch[] = $record;
            $this->rowCount++;

            if (count($batch) >= 2000) {
                ExportSalesData::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            ExportSalesData::insert($batch);
        }
    }

    private function castValue(string $fieldKey, $value)
    {
        if ($value === null || $value === '') return null;

        $numericFields = [
            'packing_quantity', 'quantity', 'price_per_unit',
            'purchase_order_value', 'purchase_order_net_value', 'freight_value',
        ];

        $intFields = ['full_container_load_count'];

        $dateFields = ['date', 'purchase_order_date', 'estimated_time_of_sailing', 'estimated_time_of_arrival'];

        if (in_array($fieldKey, $numericFields)) {
            $clean = str_replace(',', '', (string)$value);
            return is_numeric($clean) ? (float)$clean : null;
        }

        if (in_array($fieldKey, $intFields)) {
            return is_numeric($value) ? (int)$value : null;
        }

        if (in_array($fieldKey, $dateFields)) {
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

    public function chunkSize(): int { return 2000; }
    public function getRowCount(): int { return $this->rowCount; }
    public function hitRowLimit(): bool { return $this->rowCount >= $this->maxRows; }
}