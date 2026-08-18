<?php

namespace App\Http\Controllers;

use App\Models\DataRoomSection;
use App\Models\DataRoomSubsection;
use App\Models\Document;
use App\Models\PortfolioCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    private const DEFAULT_STRUCTURE = [
        [
            'legacy_key' => 'due_diligence',
            'name' => 'Due Diligence',
            'icon' => '🔍',
            'default_subsection' => 'Due Diligence Materials',
        ],
        [
            'legacy_key' => 'contracts_legal',
            'name' => 'Contracts & Legal',
            'icon' => '📜',
            'default_subsection' => 'Legal Agreements',
        ],
        [
            'legacy_key' => 'financial_documents',
            'name' => 'Financial Documents',
            'icon' => '💰',
            'default_subsection' => 'Financial Files',
        ],
        [
            'legacy_key' => 'corporate_documents',
            'name' => 'Corporate Documents',
            'icon' => '🏛️',
            'default_subsection' => 'Corporate Records',
        ],
        [
            'legacy_key' => 'operational',
            'name' => 'Operational',
            'icon' => '⚙️',
            'default_subsection' => 'Operational Files',
        ],
        [
            'legacy_key' => 'other',
            'name' => 'Other',
            'icon' => '📁',
            'default_subsection' => 'General Files',
        ],
    ];

    private const ICON_OPTIONS = [
        '📁', '📂', '📄', '📋', '📊', '📎', '🔒', '📈', '🗂️', '📝',
        '💼', '🔍', '📜', '💰', '🏛️', '⚙️', '🧾', '📦', '🗃️', '🧠',
    ];

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
        'application/zip',
        'application/x-zip-compressed',
        'application/vnd.rar',
        'application/x-rar-compressed',
        'application/x-rar',
        'application/octet-stream',
    ];

    public function index(Request $request, $companyId)
    {
        $company = $this->authorizeDocuments((int) $companyId);
        $sections = $this->ensureStructure((int) $companyId);
        $documents = $this->documentsForCompany((int) $companyId);

        return Inertia::render('Documents/DataRoom', [
            'company'      => $company,
            'documents'    => $documents->values(),
            'sections'     => $sections->values(),
            'iconOptions'  => self::ICON_OPTIONS,
            'lastUpload'   => $documents->first()['created_at'] ?? null,
        ]);
    }

    public function store(Request $request, $companyId)
    {
        $this->authorizeDocuments((int) $companyId);
        $this->ensureStructure((int) $companyId);

        $request->validate([
            'files'         => ['nullable', 'array', 'min:1'],
            'files.*'       => ['file', 'max:51200'],
            'file'          => ['nullable', 'file', 'max:51200'],
            'subsection_id' => ['required', 'integer'],
            'name'          => ['nullable', 'string', 'max:255'],
        ]);

        $files = $this->resolveUploadFiles($request);
        abort_if($files->isEmpty(), 422, 'Please choose at least one file to upload.');

        $subsection = $this->resolveSubsection((int) $companyId, (int) $request->integer('subsection_id'));
        $uploadedNames = [];

        DB::transaction(function () use ($files, $request, $companyId, $subsection, &$uploadedNames) {
            $customName = trim((string) $request->input('name', ''));
            $allowCustomName = $customName !== '' && $files->count() === 1;

            foreach ($files as $file) {
                $mime = $file->getMimeType();

                if ($mime === 'application/octet-stream' && !in_array(strtolower((string) $file->getClientOriginalExtension()), ['zip', 'rar'], true)) {
                    abort(422, 'File type not allowed. Supported: Excel, CSV, PDF, Word, PowerPoint, Images, ZIP, RAR.');
                }

                if (!in_array($mime, $this->allowedMimes, true)) {
                    abort(422, 'File type not allowed. Supported: Excel, CSV, PDF, Word, PowerPoint, Images, ZIP, RAR.');
                }

                $path = $file->store("data-room/{$companyId}", 'private');
                $displayName = $this->filenameWithExtension(
                    $allowCustomName ? $customName : $file->getClientOriginalName(),
                    $path,
                    $mime
                );

                DB::table('documents')->insert([
                    'portfolio_company_id' => $companyId,
                    'data_room_subsection_id' => $subsection->id,
                    'name' => $displayName,
                    'path' => $path,
                    'mime_type' => $mime,
                    'category' => $this->legacyCategoryForSection($subsection->section_name ?? ''),
                    'uploaded_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $uploadedNames[] = $displayName;
            }
        });

        if (count($uploadedNames) === 1) {
            return back()->with('success', "'{$uploadedNames[0]}' uploaded successfully.");
        }

        return back()->with('success', count($uploadedNames) . ' documents uploaded successfully.');
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
        $doc = $this->documentRecord((int) $companyId, (int) $documentId);
        abort_if(!$doc, 404);

        if (Storage::disk('private')->exists($doc->path)) {
            Storage::disk('private')->delete($doc->path);
        }

        DB::table('documents')->where('id', $documentId)->delete();

        return back()->with('success', "'{$doc->name}' deleted.");
    }

    public function storeSection(Request $request, $companyId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $this->ensureStructure((int) $companyId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:32'],
        ]);

        abort_unless(in_array($data['icon'], self::ICON_OPTIONS, true), 422, 'Invalid icon.');

        $sortOrder = (int) DataRoomSection::where('portfolio_company_id', $companyId)->max('sort_order') + 1;

        $section = DataRoomSection::create([
            'portfolio_company_id' => $companyId,
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort_order' => $sortOrder,
        ]);

        DataRoomSubsection::create([
            'data_room_section_id' => $section->id,
            'name' => 'General',
            'icon' => '📄',
            'sort_order' => 1,
        ]);

        return back()->with('success', 'Section created successfully.');
    }

    public function updateSection(Request $request, $companyId, $sectionId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $section = $this->resolveSection((int) $companyId, (int) $sectionId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:32'],
        ]);

        abort_unless(in_array($data['icon'], self::ICON_OPTIONS, true), 422, 'Invalid icon.');

        $section->update($data);

        return back()->with('success', 'Section updated successfully.');
    }

    public function destroySection($companyId, $sectionId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $section = $this->resolveSection((int) $companyId, (int) $sectionId);

        $this->deleteDocumentsForSubsections($section->subsections()->pluck('id'));
        $section->delete();

        return back()->with('success', 'Section deleted successfully.');
    }

    public function storeSubsection(Request $request, $companyId, $sectionId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $section = $this->resolveSection((int) $companyId, (int) $sectionId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:32'],
        ]);

        abort_unless(in_array($data['icon'], self::ICON_OPTIONS, true), 422, 'Invalid icon.');

        $sortOrder = (int) DataRoomSubsection::where('data_room_section_id', $section->id)->max('sort_order') + 1;

        DataRoomSubsection::create([
            'data_room_section_id' => $section->id,
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', 'Subsection created successfully.');
    }

    public function updateSubsection(Request $request, $companyId, $subsectionId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $subsection = $this->resolveSubsection((int) $companyId, (int) $subsectionId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:32'],
        ]);

        abort_unless(in_array($data['icon'], self::ICON_OPTIONS, true), 422, 'Invalid icon.');

        $subsection->update($data);

        return back()->with('success', 'Subsection updated successfully.');
    }

    public function destroySubsection($companyId, $subsectionId): RedirectResponse
    {
        $this->authorizeDocuments((int) $companyId);
        $subsection = $this->resolveSubsection((int) $companyId, (int) $subsectionId);

        $this->deleteDocumentsForSubsections(collect([$subsection->id]));
        $subsection->delete();

        return back()->with('success', 'Subsection deleted successfully.');
    }

    private function ensureStructure(int $companyId): Collection
    {
        if (!DataRoomSection::where('portfolio_company_id', $companyId)->exists()) {
            foreach (self::DEFAULT_STRUCTURE as $index => $definition) {
                $section = DataRoomSection::create([
                    'portfolio_company_id' => $companyId,
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'sort_order' => $index + 1,
                ]);

                DataRoomSubsection::create([
                    'data_room_section_id' => $section->id,
                    'name' => $definition['default_subsection'],
                    'icon' => '📄',
                    'sort_order' => 1,
                ]);
            }
        }

        $sections = DataRoomSection::with('subsections')
            ->where('portfolio_company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $defaultMap = collect(self::DEFAULT_STRUCTURE)
            ->keyBy('legacy_key')
            ->map(function ($definition) use ($sections) {
                $section = $sections->firstWhere('name', $definition['name']);
                return [
                    'section' => $section,
                    'subsection' => $section?->subsections->sortBy('sort_order')->first(),
                ];
            });

        DB::table('documents')
            ->where('portfolio_company_id', $companyId)
            ->whereNull('data_room_subsection_id')
            ->orderBy('id')
            ->get()
            ->each(function ($document) use ($defaultMap) {
                $legacyKey = $document->category ?: 'other';
                $target = $defaultMap->get($legacyKey) ?? $defaultMap->get('other');
                if (!$target || !$target['subsection']) {
                    return;
                }

                DB::table('documents')
                    ->where('id', $document->id)
                    ->update([
                        'data_room_subsection_id' => $target['subsection']->id,
                        'category' => $legacyKey,
                    ]);
            });

        return $this->sectionPayload((int) $companyId);
    }

    private function sectionPayload(int $companyId): Collection
    {
        $documents = $this->documentsForCompany($companyId);
        $sectionCounts = $documents->groupBy('section_id')->map->count();
        $subsectionCounts = $documents->groupBy('subsection_id')->map->count();

        return DataRoomSection::with('subsections')
            ->where('portfolio_company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (DataRoomSection $section) use ($sectionCounts, $subsectionCounts) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'icon' => $section->icon,
                    'count' => (int) ($sectionCounts[$section->id] ?? 0),
                    'subsections' => $section->subsections->map(function (DataRoomSubsection $subsection) use ($subsectionCounts) {
                        return [
                            'id' => $subsection->id,
                            'name' => $subsection->name,
                            'icon' => $subsection->icon,
                            'count' => (int) ($subsectionCounts[$subsection->id] ?? 0),
                        ];
                    })->values()->all(),
                ];
            });
    }

    private function documentsForCompany(int $companyId): Collection
    {
        return DB::table('documents')
            ->where('documents.portfolio_company_id', $companyId)
            ->leftJoin('users', 'documents.uploaded_by', '=', 'users.id')
            ->leftJoin('data_room_subsections as drs', 'drs.id', '=', 'documents.data_room_subsection_id')
            ->leftJoin('data_room_sections as ds', 'ds.id', '=', 'drs.data_room_section_id')
            ->select(
                'documents.*',
                'users.name as uploader_name',
                'drs.name as subsection_name',
                'drs.icon as subsection_icon',
                'drs.id as subsection_ref',
                'ds.name as section_name',
                'ds.icon as section_icon',
                'ds.id as section_ref'
            )
            ->orderByDesc('documents.created_at')
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'name' => $doc->name,
                'mime_type' => $doc->mime_type,
                'uploader' => $doc->uploader_name ?? 'Unknown',
                'created_at' => $doc->created_at,
                'section_id' => $doc->section_ref,
                'section_name' => $doc->section_name,
                'section_icon' => $doc->section_icon,
                'subsection_id' => $doc->subsection_ref,
                'subsection_name' => $doc->subsection_name,
                'subsection_icon' => $doc->subsection_icon,
            ]);
    }

    private function resolveSection(int $companyId, int $sectionId): DataRoomSection
    {
        return DataRoomSection::where('portfolio_company_id', $companyId)->findOrFail($sectionId);
    }

    private function resolveUploadFiles(Request $request): Collection
    {
        $files = $request->file('files');

        if (is_array($files) && count($files) > 0) {
            return collect($files)->filter();
        }

        if ($request->hasFile('file')) {
            return collect([$request->file('file')]);
        }

        return collect();
    }

    private function resolveSubsection(int $companyId, int $subsectionId): object
    {
        $subsection = DataRoomSubsection::query()
            ->select('data_room_subsections.*', 'ds.name as section_name')
            ->join('data_room_sections as ds', 'ds.id', '=', 'data_room_subsections.data_room_section_id')
            ->where('ds.portfolio_company_id', $companyId)
            ->where('data_room_subsections.id', $subsectionId)
            ->first();

        abort_if(!$subsection, 404);

        return $subsection;
    }

    private function legacyCategoryForSection(string $sectionName): string
    {
        foreach (self::DEFAULT_STRUCTURE as $definition) {
            if ($definition['name'] === $sectionName) {
                return $definition['legacy_key'];
            }
        }

        return 'other';
    }

    private function documentRecord(int $companyId, int $documentId): ?object
    {
        return DB::table('documents')
            ->where('id', $documentId)
            ->where('portfolio_company_id', $companyId)
            ->first();
    }

    private function deleteDocumentsForSubsections(Collection $subsectionIds): void
    {
        if ($subsectionIds->isEmpty()) {
            return;
        }

        $documents = DB::table('documents')
            ->whereIn('data_room_subsection_id', $subsectionIds->all())
            ->get(['id', 'path']);

        foreach ($documents as $document) {
            if ($document->path && Storage::disk('private')->exists($document->path)) {
                Storage::disk('private')->delete($document->path);
            }
        }

        DB::table('documents')->whereIn('id', $documents->pluck('id')->all())->delete();
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