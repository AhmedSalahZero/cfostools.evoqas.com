<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SurveyResponsesExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function __construct(
        protected string $surveyTitle,
        protected array $headings,
        protected array $rows,
    ) {}

    public function title(): string
    {
        return mb_substr($this->surveyTitle, 0, 31) ?: 'Survey Responses';
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
