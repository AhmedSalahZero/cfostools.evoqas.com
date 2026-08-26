<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Finds uploaded files that no database row points at any more — the residue of
 * companies deleted before the delete flow cleaned up after itself — and
 * optionally removes them.
 */
class PruneOrphanedUploads extends Command
{
    protected $signature = 'uploads:prune-orphans
                            {--delete : Actually delete the orphaned files (default is a dry run)}';

    protected $description = 'Report (or delete) uploaded files no database row references any more';

    /** [upload directory, disk, table, path column] */
    private const SOURCES = [
        ['data-room',            'private', 'documents',                 'path'],
        ['sales-uploads',        'local',   'sales_uploads',             'file_path'],
        ['expense-uploads',      'public',  'expense_uploads',           'file_path'],
        ['export-sales-uploads', 'local',   'export_sales_uploads',      'file_path'],
        ['financial-planning',   'public',  'financial_planning_models', 'file_path'],
        ['project-receipts',     'local',   'project_expenses',          'receipt_path'],
        ['investadocs',          'private', 'investadocs',               'file_path'],
    ];

    public function handle(): int
    {
        $delete     = (bool) $this->option('delete');
        $totalFiles = 0;
        $totalBytes = 0;

        foreach (self::SOURCES as [$directory, $disk, $table, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $storage = Storage::disk($disk);

            if (!$storage->directoryExists($directory)) {
                continue;
            }

            $referenced = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column)
                ->flip();

            $orphans = [];
            $bytes   = 0;

            foreach ($storage->allFiles($directory) as $file) {
                if ($referenced->has($file)) {
                    continue;
                }
                $orphans[] = $file;
                $bytes    += $storage->size($file);
            }

            if (empty($orphans)) {
                $this->line("  <fg=gray>{$directory}: clean</>");
                continue;
            }

            $this->line(sprintf(
                '  <fg=yellow>%s: %d orphaned file(s), %s</>',
                $directory,
                count($orphans),
                $this->humanBytes($bytes)
            ));

            foreach ($orphans as $file) {
                if ($delete) {
                    $storage->delete($file);
                    $this->line("      deleted {$file}");
                } else {
                    $this->line("      {$file}");
                }
            }

            $totalFiles += count($orphans);
            $totalBytes += $bytes;
        }

        if ($totalFiles === 0) {
            $this->info('No orphaned uploads found.');
            return self::SUCCESS;
        }

        $summary = sprintf('%d orphaned file(s), %s', $totalFiles, $this->humanBytes($totalBytes));

        if ($delete) {
            $this->info("Deleted {$summary}.");
        } else {
            $this->warn("Found {$summary}. Re-run with --delete to remove them.");
        }

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024)      return $bytes . ' B';
        if ($bytes < 1048576)   return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
