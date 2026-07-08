<?php

namespace App\Http\Controllers;

use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
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
        $displayName = $request->input('name') ?: $file->getClientOriginalName();

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

        DB::table('documents')
            ->where('id', $documentId)
            ->update(['name' => $request->input('name'), 'updated_at' => now()]);

        return back()->with('success', 'Document renamed successfully.');
    }

    public function download($companyId, $documentId)
    {
        $this->authorizeDocuments((int) $companyId);
        $doc = DB::table('documents')
            ->where('id', $documentId)
            ->where('portfolio_company_id', $companyId)
            ->first();
        abort_if(!$doc, 404);

        if (!Storage::disk('private')->exists($doc->path)) {
            abort(404, 'File not found on disk.');
        }

        return Storage::disk('private')->download($doc->path, $doc->name);
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
}