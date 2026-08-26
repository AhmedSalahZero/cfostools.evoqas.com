<?php

namespace App\Services;

use App\Models\PortfolioCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes every trace of a portfolio company: the rows in all company-scoped
 * tables AND the uploaded files those rows point at.
 *
 * Most foreign keys to `portfolio_companies` are ON DELETE CASCADE, so the
 * database already drops the rows — but it can never drop the files on disk,
 * and a few tables (export_sales_data, investor_evaluations) have no foreign
 * key at all while others (investadocs, user_tasks) are ON DELETE SET NULL.
 * This service closes all of those gaps in one place.
 */
class PortfolioCompanyPurger
{
    /**
     * Tables holding uploaded files, as [table, path column, disk].
     * The disk must match the one the uploading controller wrote to.
     */
    private const FILE_TABLES = [
        ['documents',                 'path',      'private'],  // Data Room
        ['sales_uploads',             'file_path', 'local'],
        ['expense_uploads',           'file_path', 'public'],
        ['export_sales_uploads',      'file_path', 'local'],
        ['financial_planning_models', 'file_path', 'public'],
        ['investadocs',               'file_path', 'private'],
    ];

    /**
     * Company-scoped tables the database will NOT cascade away, listed with the
     * column that points at the company.
     */
    private const ORPHAN_TABLES = [
        ['export_sales_data',    'portfolio_company_id'],  // no foreign key
        ['investor_evaluations', 'portfolio_company_id'],  // no foreign key
        ['investadocs',          'portfolio_company_id'],  // ON DELETE SET NULL
        ['user_tasks',           'portfolio_company_id'],  // ON DELETE SET NULL
    ];

    /**
     * Delete the company and everything belonging to it.
     *
     * File paths are collected first and the files themselves are removed only
     * after the transaction commits, so a failed delete never leaves the
     * database pointing at files that are already gone.
     *
     * @return array{files:int, directories:int} counts of what was removed
     */
    public function purge(PortfolioCompany $company): array
    {
        $files       = $this->collectFiles($company->id);
        $directories = $this->collectDirectories($company->id);

        DB::transaction(function () use ($company) {
            $this->purgeOrphanRows($company->id);
            $company->delete();   // database cascades handle the rest
        });

        $removedFiles = $this->deleteFiles($files);
        $removedDirs  = $this->deleteDirectories($directories);

        Log::info('Portfolio company purged', [
            'company_id'   => $company->id,
            'company_name' => $company->name,
            'files'        => $removedFiles,
            'directories'  => $removedDirs,
        ]);

        return ['files' => $removedFiles, 'directories' => $removedDirs];
    }

    /**
     * Every file this company owns, as ['disk' => …, 'path' => …].
     * Read before the rows are deleted — afterwards the paths are unrecoverable.
     */
    private function collectFiles(int $companyId): array
    {
        $files = [];

        foreach (self::FILE_TABLES as [$table, $column, $disk]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $paths = DB::table($table)
                ->where('portfolio_company_id', $companyId)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column);

            foreach ($paths as $path) {
                $files[] = ['disk' => $disk, 'path' => $path];
            }
        }

        // Project expense receipts hang off projects, not off the company
        if (Schema::hasTable('project_expenses')) {
            $receipts = DB::table('project_expenses')
                ->join('projects', 'projects.id', '=', 'project_expenses.project_id')
                ->where('projects.portfolio_company_id', $companyId)
                ->whereNotNull('project_expenses.receipt_path')
                ->where('project_expenses.receipt_path', '!=', '')
                ->pluck('project_expenses.receipt_path');

            foreach ($receipts as $path) {
                $files[] = ['disk' => 'local', 'path' => $path];
            }
        }

        return $files;
    }

    /**
     * Per-company upload directories, removed wholesale so files orphaned by
     * earlier bugs go too. Shared folders (sales-uploads, expense-uploads …)
     * are never listed here — they hold other companies' files.
     */
    private function collectDirectories(int $companyId): array
    {
        $directories = [
            ['disk' => 'private', 'path' => "data-room/{$companyId}"],
        ];

        if (Schema::hasTable('projects')) {
            $projectIds = DB::table('projects')
                ->where('portfolio_company_id', $companyId)
                ->pluck('id');

            foreach ($projectIds as $projectId) {
                $directories[] = ['disk' => 'local', 'path' => "project-receipts/{$projectId}"];
            }
        }

        return $directories;
    }

    /** Rows the database cascade will miss. Runs before the company is deleted. */
    private function purgeOrphanRows(int $companyId): void
    {
        foreach (self::ORPHAN_TABLES as [$table, $column]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where($column, $companyId)->delete();
        }
    }

    private function deleteFiles(array $files): int
    {
        $deleted = 0;

        foreach ($files as $file) {
            try {
                if (Storage::disk($file['disk'])->exists($file['path'])) {
                    Storage::disk($file['disk'])->delete($file['path']);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                // A missing file must never block the delete — log and move on
                Log::warning('Could not delete company file', [
                    'disk'  => $file['disk'],
                    'path'  => $file['path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }

    private function deleteDirectories(array $directories): int
    {
        $deleted = 0;

        foreach ($directories as $dir) {
            try {
                if (Storage::disk($dir['disk'])->directoryExists($dir['path'])) {
                    Storage::disk($dir['disk'])->deleteDirectory($dir['path']);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                Log::warning('Could not delete company directory', [
                    'disk'  => $dir['disk'],
                    'path'  => $dir['path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }
}
