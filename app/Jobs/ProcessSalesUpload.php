<?php

namespace App\Jobs;

use App\Imports\SalesDataImport;
use App\Models\SalesUpload;
use App\Models\SalesFieldMapping;
use App\Http\Controllers\SalesAnalysisController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessSalesUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    public $timeout = 1800; // 30 minutes — safe for large files
    public $tries = 3;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle()
{
    $upload = SalesUpload::findOrFail($this->uploadId);
    $companyId = $upload->portfolio_company_id;

    // Get active fields + labels (same logic as your controller)
    $activeFields = SalesFieldMapping::where('portfolio_company_id', $companyId)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->pluck('field_key')
        ->toArray();

    if (empty($activeFields)) {
        $activeFields = array_keys(SalesAnalysisController::FIELDS);
    }

    $fieldLabels = [];
    foreach ($activeFields as $key) {
        $fieldLabels[$key] = SalesAnalysisController::FIELDS[$key] ?? $key;
    }

    $importer = new SalesDataImport(
        $companyId,
        $upload->id,
        $activeFields,
        $fieldLabels,
        $upload->date_format
    );

    try {
        // FIXED PATH HERE ↓
        Excel::import($importer, storage_path('app/private/' . $upload->file_path));

        $upload->update([
            'row_count' => $importer->getRowCount(),
            'status'    => 'completed'
        ]);

    } catch (\Exception $e) {
        $upload->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage() . ' | Line: ' . $e->getLine()
        ]);

        throw $e;
    }
}

    public function failed(\Throwable $exception): void
    {
        $upload = SalesUpload::find($this->uploadId);
        if ($upload && $upload->status === 'processing') {
            $upload->update([
                'status'        => 'failed',
                'error_message' => 'Import failed after multiple attempts. Please check your file and try again.',
            ]);
        }
    }
}