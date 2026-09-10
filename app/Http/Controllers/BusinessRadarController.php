<?php

namespace App\Http\Controllers;

use App\Models\BusinessRadarArea;
use App\Models\BusinessRadarBoard;
use App\Models\BusinessRadarItem;
use App\Models\BusinessRadarLink;
use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BusinessRadarController extends Controller
{
    private function authorizeRadar(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'business_radar');
    }

    // ─────────────────────────────────────────────────────────
    // BOARDS
    // ─────────────────────────────────────────────────────────

    public function index(PortfolioCompany $company)
    {
        $this->authorizeRadar($company);

        $boards = BusinessRadarBoard::where('portfolio_company_id', $company->id)
            ->withCount([
                'items as challenges_count' => fn ($q) => $q->where('type', 'challenge'),
                'items as potentials_count' => fn ($q) => $q->where('type', 'potential'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('BusinessRadar/Index', [
            'company' => $company->only(['id', 'name']),
            'boards'  => $boards,
        ]);
    }

    public function store(Request $request, PortfolioCompany $company)
    {
        $this->authorizeRadar($company);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $board = BusinessRadarBoard::create([
            'portfolio_company_id' => $company->id,
            'name'                 => $data['name'],
            'description'          => $data['description'] ?? null,
            'created_by'           => auth()->id(),
        ]);

        return redirect()
            ->route('business-radar.show', [$company->id, $board->id])
            ->with('flash', ['success' => 'Board created.']);
    }

    public function update(Request $request, PortfolioCompany $company, BusinessRadarBoard $board)
    {
        $this->authorizeRadar($company);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $board->update($data);

        return back()->with('flash', ['success' => 'Board updated.']);
    }

    public function destroy(PortfolioCompany $company, BusinessRadarBoard $board)
    {
        $this->authorizeRadar($company);

        $board->delete();

        return redirect()
            ->route('business-radar.index', $company->id)
            ->with('flash', ['success' => 'Board deleted.']);
    }

    public function show(PortfolioCompany $company, BusinessRadarBoard $board)
    {
        $this->authorizeRadar($company);

        $items = $board->items()
            ->with([
                'areas',
                'linksAsChallenge.potential.areas',
                'linksAsPotential.challenge.areas',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (BusinessRadarItem $item) {
                return [
                    'id'                      => $item->id,
                    'type'                    => $item->type,
                    'title'                   => $item->title,
                    'description'             => $item->description,
                    'impact_score'            => $item->impact_score, // highest across linked areas
                    'duration_months'         => $item->duration_months,
                    'duration_label'          => $item->duration_label,
                    'duration_rank'           => $item->duration_rank,
                    'speed_score'             => $item->speed_score,
                    'priority_score'          => $item->priority_score,
                    'combined_priority_score' => $item->combined_priority_score,
                    'status'                  => $item->status,
                    'notes'                   => $item->notes,
                    'phase'                   => $item->phase,
                    'phase_label'             => $item->phase_label,
                    'areas'                   => $item->areas->map(fn ($a) => [
                        'id'           => $a->id,
                        'name'         => $a->name,
                        'impact_score' => $a->pivot->impact_score,
                    ]),
                    'links' => $item->type === 'challenge'
                        ? $item->linksAsChallenge->map(fn ($l) => [
                            'link_id'     => $l->id,
                            'strength'    => $l->strength,
                            'item_id'     => $l->potential_item_id,
                            'title'       => $l->potential?->title,
                        ])
                        : $item->linksAsPotential->map(fn ($l) => [
                            'link_id'     => $l->id,
                            'strength'    => $l->strength,
                            'item_id'     => $l->challenge_item_id,
                            'title'       => $l->challenge?->title,
                        ]),
                ];
            });

        $areas = BusinessRadarArea::forOrg($company->organization_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('BusinessRadar/Show', [
            'company'         => $company->only(['id', 'name']),
            'board'           => $board->only(['id', 'name', 'description']),
            'items'           => $items,
            'areas'           => $areas,
            'durationOptions' => collect(BusinessRadarItem::DURATION_OPTIONS)
                ->map(fn ($label, $months) => ['months' => $months, 'label' => $label])
                ->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ITEMS (Challenges & Potentials)
    // ─────────────────────────────────────────────────────────

    public function storeItem(Request $request, PortfolioCompany $company, BusinessRadarBoard $board)
    {
        $this->authorizeRadar($company);

        $data = $request->validate([
            'type'                          => ['required', Rule::in(['challenge', 'potential'])],
            'title'                         => 'required|string|max:255',
            'description'                   => 'nullable|string',
            'duration_months'               => ['required', Rule::in(array_keys(BusinessRadarItem::DURATION_OPTIONS))],
            'status'                        => 'nullable|string|max:32',
            'notes'                         => 'nullable|string',
            'areas'                         => 'required|array|min:1',
            'areas.*.business_radar_area_id' => 'required|exists:business_radar_areas,id',
            'areas.*.impact_score'           => 'required|integer|min:1|max:5',
        ]);

        $data['status'] = $data['status'] ?? 'open';
        $areas = $data['areas'];
        unset($data['areas']);

        $item = $board->items()->create(array_merge($data, [
            'created_by' => auth()->id(),
        ]));

        $item->areas()->sync($this->areasSyncPayload($areas));

        return back()->with('flash', ['success' => 'Item added.']);
    }

    public function updateItem(Request $request, PortfolioCompany $company, BusinessRadarBoard $board, BusinessRadarItem $item)
    {
        $this->authorizeRadar($company);
        abort_unless($item->business_radar_board_id === $board->id, 404);

        $data = $request->validate([
            'title'                         => 'required|string|max:255',
            'description'                   => 'nullable|string',
            'duration_months'               => ['required', Rule::in(array_keys(BusinessRadarItem::DURATION_OPTIONS))],
            'status'                        => 'required|string|max:32',
            'notes'                         => 'nullable|string',
            'areas'                         => 'required|array|min:1',
            'areas.*.business_radar_area_id' => 'required|exists:business_radar_areas,id',
            'areas.*.impact_score'           => 'required|integer|min:1|max:5',
        ]);

        $areas = $data['areas'];
        unset($data['areas']);

        $item->update($data);
        $item->areas()->sync($this->areasSyncPayload($areas));

        return back()->with('flash', ['success' => 'Item updated.']);
    }

    /**
     * Turn [['business_radar_area_id' => X, 'impact_score' => Y], ...]
     * into the ['area_id' => ['impact_score' => Y], ...] shape sync() needs.
     */
    private function areasSyncPayload(array $areas): array
    {
        $payload = [];
        foreach ($areas as $a) {
            $payload[$a['business_radar_area_id']] = ['impact_score' => $a['impact_score']];
        }
        return $payload;
    }

    public function destroyItem(PortfolioCompany $company, BusinessRadarBoard $board, BusinessRadarItem $item)
    {
        $this->authorizeRadar($company);
        abort_unless($item->business_radar_board_id === $board->id, 404);

        $item->delete();

        return back()->with('flash', ['success' => 'Item removed.']);
    }

    // ─────────────────────────────────────────────────────────
    // LINKS (Challenge ⇄ Potential)
    // ─────────────────────────────────────────────────────────

    public function storeLink(Request $request, PortfolioCompany $company, BusinessRadarBoard $board)
    {
        $this->authorizeRadar($company);

        $data = $request->validate([
            'challenge_item_id' => 'required|exists:business_radar_items,id',
            'potential_item_id' => 'required|exists:business_radar_items,id',
            'strength'           => 'required|integer|min:1|max:3',
            'note'               => 'nullable|string|max:255',
        ]);

        $challenge = BusinessRadarItem::findOrFail($data['challenge_item_id']);
        $potential = BusinessRadarItem::findOrFail($data['potential_item_id']);

        abort_unless(
            $challenge->business_radar_board_id === $board->id
            && $potential->business_radar_board_id === $board->id
            && $challenge->type === 'challenge'
            && $potential->type === 'potential',
            422,
            'Invalid link.'
        );

        BusinessRadarLink::updateOrCreate(
            [
                'challenge_item_id' => $challenge->id,
                'potential_item_id' => $potential->id,
            ],
            [
                'strength' => $data['strength'],
                'note'     => $data['note'] ?? null,
            ]
        );

        return back()->with('flash', ['success' => 'Linked.']);
    }

    public function destroyLink(PortfolioCompany $company, BusinessRadarBoard $board, BusinessRadarLink $link)
    {
        $this->authorizeRadar($company);
        $link->delete();

        return back()->with('flash', ['success' => 'Link removed.']);
    }

    // ─────────────────────────────────────────────────────────
    // CUSTOM BUSINESS AREAS (per organization)
    // ─────────────────────────────────────────────────────────

    public function storeArea(Request $request, PortfolioCompany $company)
    {
        $this->authorizeRadar($company);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $area = BusinessRadarArea::create([
            'organization_id' => $company->organization_id,
            'name'            => $data['name'],
            'slug'            => \Illuminate\Support\Str::slug($data['name']) . '-' . $company->organization_id,
            'sort_order'      => 999,
            'is_active'       => true,
        ]);

        return back()->with('flash', ['success' => 'Area added.'])->with('newArea', $area);
    }

    public function destroyArea(PortfolioCompany $company, BusinessRadarArea $area)
    {
        $this->authorizeRadar($company);

        abort_unless((int) $area->organization_id === (int) $company->organization_id, 403);

        $area->delete();

        return back()->with('flash', ['success' => 'Area removed.']);
    }
}
