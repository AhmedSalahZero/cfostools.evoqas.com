<?php

namespace App\Jobs;

use App\Imports\ExpenseDataImport;
use App\Models\ExpenseUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExpenseUpload implements ShouldQueue
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
        $upload = ExpenseUpload::findOrFail($this->uploadId);

        $importer = new ExpenseDataImport(
            $upload->portfolio_company_id,
            $upload->id,
            $upload->date_format ?? 'DD/MM/YYYY'
        );

        try {
            // Use the same private disk path as sales
            Excel::import($importer, storage_path('app/public/' . $upload->file_path));

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
        $upload = ExpenseUpload::find($this->uploadId);
        if ($upload && $upload->status === 'processing') {
            $upload->update([
                'status'        => 'failed',
                'error_message' => 'Import failed after multiple attempts. Please check your file and try again.',
            ]);
        }
    }
}


