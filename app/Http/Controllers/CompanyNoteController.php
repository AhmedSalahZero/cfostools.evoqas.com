<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPortfolioCompany;
use App\Models\PortfolioCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyNoteController extends Controller
{
    use AuthorizesPortfolioCompany;

    public function index(int $company)
    {
        $model = $this->authorizeCompany($company, 'view_dashboard');

        $notes = DB::table('portfolio_company_notes as n')
            ->where('n.portfolio_company_id', $model->id)
            ->join('users', 'users.id', '=', 'n.created_by')
            ->select('n.*', 'users.name as author')
            ->orderByDesc('n.created_at')
            ->get()
            ->map(fn ($n) => [
                'id'           => $n->id,
                'note'         => $n->note,
                'action_items' => $n->action_items,
                'priority'     => $n->priority,
                'author'       => $n->author,
                'created_at'   => Carbon::parse($n->created_at)->format('d M Y'),
            ]);

        return response()->json(['notes' => $notes]);
    }

    public function store(Request $request, int $company)
    {
        $model = $this->authorizeCompany($company, 'view_dashboard');

        $request->validate([
            'note'         => ['required', 'string', 'max:10000'],
            'action_items' => ['nullable', 'string', 'max:1000'],
            'priority'     => ['required', 'in:high,medium,low'],
        ]);

        DB::table('portfolio_company_notes')->insert([
            'portfolio_company_id' => $model->id,
            'note'                 => $request->note,
            'action_items'         => $request->action_items,
            'priority'             => $request->priority,
            'created_by'           => auth()->id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $company, int $note)
    {
        $model = $this->authorizeCompany($company, 'view_dashboard');

        $existing = DB::table('portfolio_company_notes')
            ->where('id', $note)
            ->where('portfolio_company_id', $model->id)
            ->first();

        abort_unless($existing, 404);

        $user = auth()->user();
        if (
            (int) $existing->created_by !== (int) $user->id
            && !$user->hasAnyRole(['super-admin', 'admin'])
        ) {
            abort(403, 'You can only delete your own notes.');
        }

        DB::table('portfolio_company_notes')
            ->where('id', $note)
            ->where('portfolio_company_id', $model->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
