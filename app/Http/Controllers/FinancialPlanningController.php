<?php

namespace App\Http\Controllers;

use App\Models\FinancialPlanningModel;
use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class FinancialPlanningController extends Controller
{
    private function authorizePlanning(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'financial_planning');
    }

    // =========================================================================
    // INDEX — list all models for a company
    // =========================================================================
    public function index(PortfolioCompany $company)
    {
        $this->authorizePlanning($company);
        $models = FinancialPlanningModel::where('portfolio_company_id', $company->id)
            ->latest()
            ->get()
            ->map(fn($m) => [
                'id'                => $m->id,
                'name'              => $m->name,
                'model_type'        => $m->model_type,
                'original_filename' => $m->original_filename,
                'version'           => $m->version,
                'uploaded_at'       => $m->created_at->format('d M Y H:i'),
                'uploader'          => $m->uploader?->name ?? 'System',
            ]);

        return Inertia::render('FinancialPlanning/Index', [
            'company' => $company,
            'models'  => $models,
        ]);
    }

    // =========================================================================
    // UPLOAD PAGE
    // =========================================================================
    public function uploadPage(PortfolioCompany $company)
    {
        $this->authorizePlanning($company);
        return Inertia::render('FinancialPlanning/Upload', [
            'company' => $company,
        ]);
    }

    // =========================================================================
    // PROCESS UPLOAD
    // =========================================================================
    public function processUpload(Request $request, PortfolioCompany $company)
    {
        $this->authorizePlanning($company);
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls|max:20480',
            'name'       => 'required|string|max:255',
            'model_type' => 'required|in:complex,simple',
            'version'    => 'nullable|string|max:50',
            'notes'      => 'nullable|string',
        ]);

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();

        $path = $file->storeAs(
            'financial-planning/' . $company->id,
            $filename,
            'public'
        );

        FinancialPlanningModel::create([
            'portfolio_company_id' => $company->id,
            'uploaded_by'          => auth()->id(),
            'name'                 => $request->name,
            'model_type'           => $request->model_type,
            'original_filename'    => $filename,
            'file_path'            => $path,
            'version'              => $request->version,
            'notes'                => $request->notes,
        ]);

        return redirect()->route('financial-planning.index', $company->id)
            ->with('flash', ['success' => 'Model uploaded successfully.']);
    }

    // =========================================================================
    // DELETE — remove model record + file from disk
    // =========================================================================
    public function destroy(PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        // Delete the physical file from storage
        if (Storage::disk('public')->exists($model->file_path)) {
            Storage::disk('public')->delete($model->file_path);
        }

        // Clear any cached data for this model
        Cache::forget('fp_assumptions_' . $model->id);
        Cache::forget('fp_live_' . $model->id);

        $model->delete();

        return redirect()->route('financial-planning.index', $company->id)
            ->with('flash', ['success' => 'Model deleted successfully.']);
    }

    // =========================================================================
    // ASSUMPTION EDITOR — for Complex models
    // Reads Assumption_Sheet, finds Input→ rows, renders a structured form
    // =========================================================================
    public function assumptionEditor(PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('public')->path($model->file_path);

        if (!file_exists($fullPath)) {
            return redirect()->route('financial-planning.index', $company->id)
                ->with('flash', ['error' => 'Model file not found. Please re-upload.']);
        }

        $cacheKey    = 'fp_assumptions_' . $model->id . '_' . filemtime($fullPath);
        // $assumptions = Cache::remember($cacheKey, 600, fn() => $this->parseAssumptions($fullPath));
        $assumptions = $this->parseAssumptions($fullPath);   // ← bypass cache for debugging
        return Inertia::render('FinancialPlanning/AssumptionEditor', [
            'company'     => $company,
            'model'       => $model,
            'assumptions' => $assumptions,
        ]);
    }

    // =========================================================================
    // PRIVATE: Parse Assumption_Sheet into sections with Input→ rows
    // =========================================================================
    
    private function parseAssumptions(string $fullPath): array
    {
        $inputFileType = IOFactory::identify($fullPath);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(false);

        $spreadsheet = $reader->load($fullPath);

        // ── Flexible sheet name matching ─────────────────────────────────────
        $ws = null;
        $target = 'assumption';

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $title = trim($worksheet->getTitle());
            $lower = strtolower($title);

            $score = 0;
            if (str_contains($lower, $target)) $score += 10;
            if (str_contains($lower, 'sheet'))  $score += 4;
            if (str_contains($lower, 'input') || str_contains($lower, 'assump')) $score += 3;
            if (preg_match('/assump.*sheet/i', $title)) $score += 15;

            if ($score >= 10) {
                $ws = $worksheet;
                break;
            }
        }

        // Fallback
        $ws ??= $spreadsheet->getSheet(0);

        $sections       = [];
        $currentSection = 'General';
        $sectionItems   = [];
        $maxRow         = $ws->getHighestDataRow();

        for ($r = 1; $r <= $maxRow; $r++) {
            $colB = $ws->getCell('B' . $r)->getValue();
            $colC = $ws->getCell('C' . $r)->getValue();
            $colD = $ws->getCell('D' . $r)->getValue();
            $colE = $ws->getCell('E' . $r)->getCalculatedValue();

            $colBStr = trim((string)($colB ?? ''));
            $colCStr = trim((string)($colC ?? ''));
            $colDStr = trim((string)($colD ?? ''));

            // Section header
            if ($colBStr && !$colD && !$colE && strlen($colBStr) > 2 && !str_starts_with($colBStr, '=')) {
                if (!empty($sectionItems)) {
                    $sections[] = ['title' => $currentSection, 'items' => $sectionItems];
                    $sectionItems = [];
                }
                $currentSection = $colBStr;
                continue;
            }

            // Single-year Input→ in col D
            if (str_contains($colDStr, 'Input') && $colBStr) {
                $rawE = $ws->getCell('E' . $r)->getValue();
                if (is_string($rawE) && str_starts_with($rawE, '=')) continue;
                if (in_array(strtolower($colBStr), ['item', 'input ↓', 'input→', 'input ↓'])) continue;

                $sectionItems[] = [
                    'row'        => $r,
                    'col'        => 'E',
                    'label'      => $colBStr,
                    'unit'       => $colCStr,
                    'value'      => $colE,
                    'type'       => $this->detectInputType($colCStr, $colE),
                    'multi_year' => false,
                ];
                continue;
            }

            // Multi-year Input→ in col C
            if (str_contains($colCStr, 'Input') && $colBStr && !str_contains($colDStr, 'Input')) {
                if (in_array(strtolower($colBStr), ['item', 'input ↓', 'input→'])) continue;

                $yearValues = [];
                for ($c = 5; $c <= 9; $c++) {
                    $cell = $ws->getCellByColumnAndRow($c, $r);
                    $raw  = $cell->getValue();
                    if ($raw !== null && !(is_string($raw) && str_starts_with($raw, '='))) {
                        $yearValues[] = [
                            'col'   => Coordinate::stringFromColumnIndex($c),
                            'value' => $cell->getCalculatedValue(),
                        ];
                    }
                }

                if (!empty($yearValues)) {
                    $sectionItems[] = [
                        'row'        => $r,
                        'col'        => 'E',
                        'label'      => $colBStr,
                        'unit'       => $colCStr,
                        'value'      => $yearValues[0]['value'] ?? null,
                        'year_values'=> $yearValues,
                        'type'       => $this->detectInputType($colCStr, $yearValues[0]['value'] ?? null),
                        'multi_year' => true,
                    ];
                }
            }
        }

        if (!empty($sectionItems)) {
            $sections[] = ['title' => $currentSection, 'items' => $sectionItems];
        }

        return array_values(array_filter($sections, fn($s) => !empty($s['items'])));
    }


    // =========================================================================
    // PRIVATE: Detect input field type for form rendering
    // =========================================================================
    private function detectInputType(string $unit, mixed $value): string
    {
        $u = strtolower(trim($unit));
        if ($u === '%')        return 'percent';
        if ($u === 'dt')       return 'date';
        if ($u === 'currency') return 'text';
        if ($u === 'text')     return 'text';
        if ($u === 'mths')     return 'number';
        if (is_numeric($value)) return 'number';
        return 'text';
    }

    // =========================================================================
    // SAVE ASSUMPTIONS — write changed assumption values back to Excel
    // =========================================================================
    public function saveAssumptions(Request $request, PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        $request->validate([
            'changes' => 'required|array',
            // Each change: { row: int, col: string, value: mixed, unit: string }
        ]);

        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('public')->path($model->file_path);

        if (!file_exists($fullPath)) {
            return back()->with('flash', ['error' => 'File not found. Cannot save.']);
        }

        $spreadsheet = IOFactory::load($fullPath);
        $ws          = $spreadsheet->getSheetByName('Assumption_Sheet') ?? $spreadsheet->getSheet(0);

        foreach ($request->input('changes') as $change) {
            $row   = (int)($change['row'] ?? 0);
            $col   = $change['col'] ?? 'E';
            $value = $change['value'];
            $unit  = strtolower(trim((string)($change['unit'] ?? '')));

            if ($row < 1) continue;

            // Convert % display value back to decimal for storage
            if ($unit === '%' && is_numeric($value)) {
                $value = (float)$value / 100;
            }

            // Convert date string to Excel serial if needed
            if ($unit === 'dt' && $value) {
                $value = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                    strtotime($value)
                );
            }

            $ws->getCell($col . $row)->setValue($value);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fullPath);

        // Clear cache so next open reflects the saved changes
        Cache::forget('fp_assumptions_' . $model->id . '_' . filemtime($fullPath));

        return back()->with('flash', ['success' => 'Assumptions saved successfully. Download the updated Excel to run your full model in Excel.']);
    }

    // =========================================================================
    // LIVE EDITOR — for Simple models only
    // =========================================================================
    public function liveEditor(PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('public')->path($model->file_path);

        if (!file_exists($fullPath)) {
            return redirect()->route('financial-planning.index', $company->id)
                ->with('flash', ['error' => 'Model file not found. Please re-upload.']);
        }

        // Always read fresh from disk — no cache — so saved changes are always visible
        clearstatcache(true, $fullPath);
        $sheetsData = $this->readSheetsFast($fullPath);

        return Inertia::render('FinancialPlanning/LiveEditor', [
            'company' => $company,
            'model'   => $model,
            'sheets'  => $sheetsData,
        ]);
    }

    // =========================================================================
    // PRIVATE: Fast sheet reader for simple models
    // =========================================================================
    // private function readSheetsFast(string $fullPath): array
    // {
    //     $reader = new XlsxReader();
    //     $reader->setReadDataOnly(true);
    //     $reader->setIncludeCharts(false);

    //     $spreadsheet = $reader->load($fullPath);
    //     $sheetsData  = [];

    //     foreach ($spreadsheet->getWorksheetIterator() as $ws) {
    //         $maxRow = $ws->getHighestDataRow();
    //         $maxCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());

    //         $rows = [];
    //         for ($r = 1; $r <= $maxRow; $r++) {
    //             $row = [];
    //             for ($c = 1; $c <= $maxCol; $c++) {
    //                 $cell  = $ws->getCellByColumnAndRow($c, $r);
    //                 $row[] = $cell->isFormula()
    //                     ? $cell->getValue()
    //                     : (string)($cell->getValue() ?? '');
    //             }
    //             $rows[] = $row;
    //         }

    //         $sheetsData[$ws->getTitle()] = $rows;
    //     }

    //     return $sheetsData;
    // }

    // =========================================================================
    // SAVE LIVE — simple models
    // =========================================================================
    public function saveLive(Request $request, PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        $request->validate(['sheets' => 'required|array']);

        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('public')->path($model->file_path);

        if (!file_exists($fullPath)) {
            return back()->with('flash', ['error' => 'File not found. Cannot save.']);
        }

        // ── Clear ALL cache keys for this model before loading ────────────────
        // We use a wildcard approach by storing a version counter
        Cache::forget('fp_live_version_' . $model->id);

        $spreadsheet = IOFactory::load($fullPath);

        // The frontend sends sheets keyed by their REAL sheet name (from props.sheets keys)
        // which come from the original Excel sheet titles — so getSheetByName() works.
        // Fallback: if a name isn't found, try matching by sheet index (sheet_0, sheet_1, etc.)
        $allSheets = $spreadsheet->getAllSheets();

        foreach ($request->input('sheets') as $sheetName => $rows) {
            if (!is_array($rows)) continue;

            // Try exact name match first
            $worksheet = $spreadsheet->getSheetByName($sheetName);

            // Fallback: if name is "sheet_0", "sheet_1" etc., map by index
            if (!$worksheet && preg_match('/^sheet_(\d+)$/', $sheetName, $m)) {
                $idx = (int)$m[1];
                $worksheet = $allSheets[$idx] ?? null;
            }

            if (!$worksheet) continue;

            foreach ($rows as $rowIdx => $row) {
                if (!is_array($row)) continue;
                foreach ($row as $colIdx => $value) {
                    $cell = $worksheet->getCellByColumnAndRow($colIdx + 1, $rowIdx + 1);
                    if ($value === null || $value === '') {
                        $cell->setValue(null);
                    } else {
                        $cell->setValue($value);
                    }
                }
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fullPath);

        // ── Bust the read cache so next open loads fresh data ─────────────────
        // The cache key includes filemtime, so we clear all variants by
        // iterating known patterns. Simplest: just tag with a version key.
        $oldMtime = filemtime($fullPath);
        clearstatcache(true, $fullPath);
        Cache::forget('fp_live_' . $model->id . '_' . $oldMtime);
        // Also clear the new mtime key in case it was pre-cached
        Cache::forget('fp_live_' . $model->id . '_' . filemtime($fullPath));

        return back()->with('flash', ['success' => 'Model saved successfully.']);
    }

    // =========================================================================
    // PRIVATE: Fast sheet reader for Simple models (formulas preserved)
    // =========================================================================
    private function readSheetsFast(string $fullPath): array
    {
        $inputFileType = IOFactory::identify($fullPath);
        $reader = IOFactory::createReader($inputFileType);
        // Must NOT use setReadDataOnly(true) — we need formula strings preserved
        $reader->setReadDataOnly(false);
        $reader->setIncludeCharts(false);

        $spreadsheet = $reader->load($fullPath);
        $sheetsData  = [];

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $maxRow = $ws->getHighestDataRow();
            $maxCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());

            // Handle empty sheets gracefully — return empty array, not null
            if ($maxRow < 1 || $maxCol < 1) {
                $sheetsData[$ws->getTitle()] = [];
                continue;
            }

            $rows = [];
            for ($r = 1; $r <= $maxRow; $r++) {
                $row = [];
                for ($c = 1; $c <= $maxCol; $c++) {
                    $cell = $ws->getCellByColumnAndRow($c, $r);

                    if ($cell->isFormula()) {
                        // Preserve the formula string so HyperFormula can evaluate it
                        // in the browser (e.g. "=SUM(A1:A10)")
                        $row[] = $cell->getValue();          // returns "=SUM(...)"
                    } else {
                        // Raw values: numbers stay as numbers, strings as strings
                        // Convert null → '' so Handsontable never sees null
                        $raw = $cell->getValue();
                        $row[] = ($raw === null) ? '' : $raw;
                    }
                }
                $rows[] = $row;
            }

            $sheetsData[$ws->getTitle()] = $rows;
        }

        return $sheetsData;
    }





    // =========================================================================
    // DOWNLOAD
    // =========================================================================
    public function download(PortfolioCompany $company, FinancialPlanningModel $model)
    {
        $this->authorizePlanning($company);
        $fullPath = Storage::disk('public')->path($model->file_path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        return response()->download($fullPath, $model->original_filename);
    }
}