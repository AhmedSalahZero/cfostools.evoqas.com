<?php

namespace App\Http\Controllers;

use App\Models\ModelStudioWorkbook;
use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModelStudioController extends Controller
{
    private function authorizeModelStudio(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'financial_model_studio');
    }

    // List all workbooks for a company
    public function index(PortfolioCompany $company)
    {
        $this->authorizeModelStudio($company);
        $workbooks = ModelStudioWorkbook::select('id', 'name', 'last_saved_at', 'updated_at')
            ->where('portfolio_company_id', $company->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($w) => [
                'id'            => $w->id,
                'name'          => $w->name,
                'last_saved_at' => $w->last_saved_at?->format('d M Y, H:i'),
                'updated_at'    => $w->updated_at->format('d M Y, H:i'),
                // Count sheets: old format = array count, new Univer format = count sheets map
                'sheet_count'   => $this->countSheets($w->sheets_data),
            ]);

        return Inertia::render('ModelStudio/Index', [
            'company'   => ['id' => $company->id, 'name' => $company->name],
            'workbooks' => $workbooks,
        ]);
    }

    // Create a new blank workbook
    public function store(Request $request, PortfolioCompany $company)
    {
        $this->authorizeModelStudio($company);
        $request->validate(['name' => 'required|string|max:255']);

        $workbook = ModelStudioWorkbook::create([
            'portfolio_company_id' => $company->id,
            'created_by'           => auth()->id(),
            'name'                 => $request->name,
            'sheets_data'          => null,  // Univer will create the blank sheet on mount
            'charts_data'          => [],
            'last_saved_at'        => now(),
        ]);

        return redirect()->route('model-studio.editor', [$company->id, $workbook->id]);
    }

    // Open the editor for a specific workbook
    public function editor(PortfolioCompany $company, ModelStudioWorkbook $workbook)
    {
        $this->authorizeModelStudio($company);
        abort_unless((int) $workbook->portfolio_company_id === (int) $company->id, 404);
        return Inertia::render('ModelStudio/Editor', [
            'company'  => ['id' => $company->id, 'name' => $company->name],
            'workbook' => [
                'id'          => $workbook->id,
                'name'        => $workbook->name,
                // Pass raw — Editor.vue detects old vs new format automatically
                'sheets_data' => $workbook->sheets_data,
                'charts_data' => $workbook->charts_data ?? [],
            ],
        ]);
    }

    // Save workbook data (called via fetch from the editor)
    public function save(Request $request, PortfolioCompany $company, ModelStudioWorkbook $workbook)
    {
        $this->authorizeModelStudio($company);
        abort_unless((int) $workbook->portfolio_company_id === (int) $company->id, 404);
        $request->validate([
            'sheets_data' => 'nullable',        // can be array (old) or object (Univer snapshot)
            'charts_data' => 'nullable|array',
            'name'        => 'nullable|string|max:255',
        ]);

        $workbook->update([
            'sheets_data'   => $request->sheets_data,
            'charts_data'   => $request->charts_data ?? [],
            'name'          => $request->name ?? $workbook->name,
            'last_saved_at' => now(),
        ]);

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    // Rename workbook
    public function rename(Request $request, PortfolioCompany $company, ModelStudioWorkbook $workbook)
    {
        $this->authorizeModelStudio($company);
        abort_unless((int) $workbook->portfolio_company_id === (int) $company->id, 404);
        $request->validate(['name' => 'required|string|max:255']);
        $workbook->update(['name' => $request->name]);
        return back()->with('success', 'Workbook renamed.');
    }

    // Delete workbook
    public function destroy(PortfolioCompany $company, ModelStudioWorkbook $workbook)
    {
        $this->authorizeModelStudio($company);
        abort_unless((int) $workbook->portfolio_company_id === (int) $company->id, 404);
        $workbook->delete();
        return back()->with('success', 'Workbook deleted.');
    }

    // ── Helper: count sheets regardless of storage format ──
    private function countSheets($sheetsData): int
    {
        if (!$sheetsData) return 0;

        // Old Handsontable format: plain array of sheet objects
        if (isset($sheetsData[0])) {
            return count($sheetsData);
        }

        // New Univer snapshot format: object with a 'sheets' map
        if (isset($sheetsData['sheets']) && is_array($sheetsData['sheets'])) {
            return count($sheetsData['sheets']);
        }

        return 0;
    }
}