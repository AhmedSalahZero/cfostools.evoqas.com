<?php

namespace App\Jobs;

use App\Imports\ExportSalesDataImport;
use App\Models\ExportSalesUpload;
use App\Http\Controllers\ExportSalesAnalysisController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExportSalesUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    public $timeout = 1800;
    public $tries   = 3;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle()
    {
        $upload    = ExportSalesUpload::findOrFail($this->uploadId);
        $companyId = $upload->portfolio_company_id;

        $activeFields = \App\Models\ExportSalesFieldMapping::where('portfolio_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('field_key')
            ->toArray();

        if (empty($activeFields)) {
            $activeFields = array_keys(ExportSalesAnalysisController::FIELDS);
        }

        $fieldLabels = [];
        foreach ($activeFields as $key) {
            $fieldLabels[$key] = ExportSalesAnalysisController::FIELDS[$key] ?? $key;
        }

        $importer = new ExportSalesDataImport(
            $companyId,
            $upload->id,
            $activeFields,
            $fieldLabels,
            $upload->date_format
        );

        try {
            Excel::import($importer, storage_path('app/private/' . $upload->file_path));

            $upload->update([
                'row_count' => $importer->getRowCount(),
                'status'    => 'completed',
            ]);

        } catch (\Exception $e) {
            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage() . ' | Line: ' . $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $upload = ExportSalesUpload::find($this->uploadId);
        if ($upload && $upload->status === 'processing') {
            $upload->update([
                'status'        => 'failed',
                'error_message' => 'Import failed after multiple attempts. Please check your file and try again.',
            ]);
        }
    }
}