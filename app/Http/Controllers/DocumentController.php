<?php

namespace App\Http\Controllers;

use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DocumentController extends Controller
{
    /** Upper bound on grid size the in-browser editor will attempt to load. */
    private const MAX_EDITABLE_CELLS = 400000;

    private function authorizeDocuments(int $companyId): object
    {
        $company = $this->authorizeCompany($companyId, 'documents');
        return (object) ['id' => $company->id, 'name' => $company->name];
    }

    private array $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv', 'text/plain',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
    ];

    private array $sections = [
        'due_diligence'       => 'Due Diligence',
        'contracts_legal'     => 'Contracts & Legal',
        'financial_documents' => 'Financial Documents',
        'corporate_documents' => 'Corporate Documents',
        'operational'         => 'Operational',
        'other'               => 'Other',
    ];

    public function index(Request $request, $companyId)
    {
        $company = $this->authorizeDocuments((int) $companyId);

        $documents = DB::table('documents')
            ->where('portfolio_company_id', $companyId)
            ->leftJoin('users', 'documents.uploaded_by', '=', 'users.id')
            ->select('documents.*', 'users.name as uploader_name')
            ->orderByDesc('documents.created_at')
            ->get()
            ->map(fn($doc) => [
                'id'         => $doc->id,
                'name'       => $doc->name,
                'category'   => $doc->category,
                'mime_type'  => $doc->mime_type,
                'uploader'   => $doc->uploader_name ?? 'Unknown',
                'created_at' => $doc->created_at,
            ]);

        $sectionCounts = [];
        foreach (array_keys($this->sections) as $key) {
            $sectionCounts[$key] = $documents->where('category', $key)->count();
        }

        return Inertia::render('Documents/DataRoom', [
            'company'       => $company,
            'documents'     => $documents->values(),
            'sections'      => $this->sections,
            'sectionCounts' => $sectionCounts,
            'lastUpload'    => $documents->first()?->created_at ?? null,
        ]);
    }

    public function store(Request $request, $companyId)
    {
        $this->authorizeDocuments((int) $companyId);

        $request->validate([
            'file'     => ['required', 'file', 'max:51200'],
            'category' => ['required', 'in:' . implode(',', array_keys($this->sections))],
            'name'     => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();

        if (!in_array($mime, $this->allowedMimes)) {
            return back()->with('error', 'File type not allowed. Supported: Excel, CSV, PDF, Word, PowerPoint, Images.');
        }

        $path        = $file->store("data-room/{$companyId}", 'private');
        $displayName = $this->filenameWithExtension(
            $request->input('name') ?: $file->getClientOriginalName(),
            $path,
            $mime
        );

        DB::table('documents')->insert([
            'portfolio_company_id' => $companyId,
            'name'                 => $displayName,
            'path'                 => $path,
            'mime_type'            => $mime,
            'category'             => $request->input('category'),
            'uploaded_by'          => auth()->id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return back()->with('success', "'{$displayName}' uploaded successfully.");
    }

    public function rename(Request $request, $companyId, $documentId)
    {
        $this->authorizeDocuments((int) $companyId);
        $doc = DB::table('documents')
            ->where('id', $documentId)
            ->where('portfolio_company_id', $companyId)
            ->first();
        abort_if(!$doc, 404);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $name = $this->filenameWithExtension(
            $request->input('name'),
            $doc->path,
            $doc->mime_type
        );

        DB::table('documents')
            ->where('id', $documentId)
            ->update(['name' => $name, 'updated_at' => now()]);

        return back()->with('success', 'Document renamed successfully.');
    }

    public function download($companyId, $documentId)
    {
        $doc = $this->resolveDocument((int) $companyId, $documentId);

        return Storage::disk('private')->download($doc->path, $this->downloadFilename($doc));
    }

    public function view($companyId, $documentId)
    {
        $doc = $this->resolveDocument((int) $companyId, $documentId);

        $filename = $this->downloadFilename($doc);
        $mime     = $doc->mime_type ?: Storage::disk('private')->mimeType($doc->path);

        return Storage::disk('private')->response(
            $doc->path,
            $filename,
            ['Content-Type' => $mime ?: 'application/octet-stream'],
            'inline'
        );
    }

    // =========================================================================
    // SPREADSHEET EDITING — read the workbook as JSON for the in-viewer editor
    // =========================================================================
    public function sheets($companyId, $documentId)
    {
        $doc = $this->resolveDocument((int) $companyId, $documentId);
        abort_unless($this->isEditableSpreadsheet($doc), 422, 'This file cannot be edited as a spreadsheet.');

        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('private')->path($doc->path);
        clearstatcache(true, $fullPath);

        return response()->json(['sheets' => $this->readSheets($fullPath)]);
    }

    // =========================================================================
    // SPREADSHEET EDITING — write the edited cells back into the original file
    // =========================================================================
    public function saveSheets(Request $request, $companyId, $documentId)
    {
        $doc = $this->resolveDocument((int) $companyId, $documentId);
        abort_unless($this->isEditableSpreadsheet($doc), 422, 'This file cannot be edited as a spreadsheet.');

        $request->validate(['sheets' => ['required', 'array']]);

        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('private')->path($doc->path);

        // Load with styles intact so untouched formatting survives the round-trip
        $spreadsheet = IOFactory::load($fullPath);
        $allSheets   = $spreadsheet->getAllSheets();

        foreach ($request->input('sheets') as $sheetName => $rows) {
            if (!is_array($rows)) {
                continue;
            }

            $worksheet = $spreadsheet->getSheetByName((string) $sheetName);

            // Fallback for sheets sent back as "sheet_0", "sheet_1", …
            if (!$worksheet && preg_match('/^sheet_(\d+)$/', (string) $sheetName, $m)) {
                $worksheet = $allSheets[(int) $m[1]] ?? null;
            }

            if (!$worksheet) {
                continue;
            }

            // Remember the old data extent so cells the user emptied get cleared
            $oldMaxRow = $worksheet->getHighestDataRow();
            $oldMaxCol = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

            $newMaxRow = 0;
            $newMaxCol = 0;

            foreach ($rows as $rowIdx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $newMaxRow = max($newMaxRow, (int) $rowIdx + 1);
                $newMaxCol = max($newMaxCol, count($row));

                foreach ($row as $colIdx => $value) {
                    $cell = $worksheet->getCellByColumnAndRow((int) $colIdx + 1, (int) $rowIdx + 1);
                    $cell->setValue(($value === null || $value === '') ? null : $value);
                }
            }

            // Blank out anything left over outside the edited range
            for ($r = 1; $r <= $oldMaxRow; $r++) {
                for ($c = 1; $c <= $oldMaxCol; $c++) {
                    if ($r <= $newMaxRow && $c <= $newMaxCol) {
                        continue;
                    }
                    $worksheet->getCellByColumnAndRow($c, $r)->setValue(null);
                }
            }
        }

        IOFactory::createWriter($spreadsheet, $this->writerTypeFor($doc))->save($fullPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        DB::table('documents')->where('id', $doc->id)->update(['updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "'{$doc->name}' saved successfully.",
        ]);
    }

    public function destroy($companyId, $documentId)
    {
        $this->authorizeDocuments((int) $companyId);
        $doc = DB::table('documents')
            ->where('id', $documentId)
            ->where('portfolio_company_id', $companyId)
            ->first();
        abort_if(!$doc, 404);

        if (Storage::disk('private')->exists($doc->path)) {
            Storage::disk('private')->delete($doc->path);
        }

        DB::table('documents')->where('id', $documentId)->delete();

        return back()->with('success', "'{$doc->name}' deleted.");
    }

    /**
     * Only real spreadsheets (xlsx / xls / csv) can go through the live editor.
     * Checked on the extension so a .txt uploaded as text/csv is not treated as a grid.
     */
    private function isEditableSpreadsheet(object $doc): bool
    {
        return in_array($this->resolveExtension($doc->path ?? null, $doc->mime_type ?? null), ['xlsx', 'xls', 'csv'], true);
    }

    private function writerTypeFor(object $doc): string
    {
        return match ($this->resolveExtension($doc->path ?? null, $doc->mime_type ?? null)) {
            'xls'   => 'Xls',
            'csv'   => 'Csv',
            default => 'Xlsx',
        };
    }

    /**
     * Read a workbook into { sheetName: { rows: [[..]], formats: { "r,c": "#,##0.00" } } }.
     * Formulas are kept as strings ("=SUM(A1:A5)") so Univer evaluates them live and
     * they survive the round-trip back into Excel.
     */
    private function readSheets(string $fullPath): array
    {
        $reader = IOFactory::createReader(IOFactory::identify($fullPath));
        // Must NOT use setReadDataOnly(true) — number formats and formulas are needed
        $reader->setReadDataOnly(false);
        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }

        $spreadsheet = $reader->load($fullPath);
        $sheets      = [];

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $maxRow = $ws->getHighestDataRow();
            $maxCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());

            if ($maxRow < 1 || $maxCol < 1) {
                $sheets[$ws->getTitle()] = ['rows' => [], 'formats' => (object) []];
                continue;
            }

            abort_if(
                $maxRow * $maxCol > self::MAX_EDITABLE_CELLS,
                422,
                'This spreadsheet is too large to edit in the browser. Download it instead.'
            );

            // calculateFormulas=false keeps "=SUM(...)"; formatData=false keeps raw values
            $rows = $ws->rangeToArray(
                'A1:' . $ws->getHighestDataColumn() . $maxRow,
                '',
                false,
                false,
                false
            );

            // Number formats, read via the style index so we only touch cells that exist
            $formats  = [];
            $fmtByXf  = [];
            foreach ($ws->getCoordinates(false) as $coord) {
                $xf = $ws->getCell($coord)->getXfIndex();
                if (!array_key_exists($xf, $fmtByXf)) {
                    $fmtByXf[$xf] = $spreadsheet->getCellXfByIndex($xf)?->getNumberFormat()->getFormatCode() ?? '';
                }
                $code = $fmtByXf[$xf];
                if ($code === '' || $code === NumberFormat::FORMAT_GENERAL) {
                    continue;
                }
                [$col, $row] = Coordinate::coordinateFromString($coord);
                $formats[((int) $row - 1) . ',' . (Coordinate::columnIndexFromString($col) - 1)] = $code;
            }

            $sheets[$ws->getTitle()] = [
                'rows'    => $rows,
                'formats' => $formats ?: (object) [],
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $sheets;
    }

    private function resolveDocument(int $companyId, $documentId): object
    {
        $this->authorizeDocuments($companyId);

        $doc = DB::table('documents')
            ->where('id', $documentId)
            ->where('portfolio_company_id', $companyId)
            ->first();
        abort_if(!$doc, 404);

        if (!Storage::disk('private')->exists($doc->path)) {
            abort(404, 'File not found on disk.');
        }

        return $doc;
    }

    private function downloadFilename(object $doc): string
    {
        return $this->filenameWithExtension(
            $doc->name ?? '',
            $doc->path ?? null,
            $doc->mime_type ?? null
        );
    }

    private function filenameWithExtension(string $name, ?string $path = null, ?string $mime = null): string
    {
        $name = trim($name);
        $ext  = $this->resolveExtension($path, $mime);

        if ($ext === '' || $name === '') {
            return $name;
        }

        if (str_ends_with(strtolower($name), '.' . strtolower($ext))) {
            return $name;
        }

        return rtrim($name, '. ') . '.' . $ext;
    }

    private function resolveExtension(?string $path, ?string $mime): string
    {
        if ($path) {
            $fromPath = pathinfo($path, PATHINFO_EXTENSION);
            if ($fromPath !== '') {
                return strtolower($fromPath);
            }
        }

        return $this->extensionFromMime($mime ?? '');
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'text/csv' => 'csv',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-powerpoint' => 'ppt',
            default => '',
        };
    }
}