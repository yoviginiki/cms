<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Grid\Services\GridPresetSeeder;
use App\Http\Controllers\Controller;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\GridPosition;
use App\Models\PositionOverride;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GridController extends Controller
{
    // ─── GRIDS CRUD ───

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $grids = $site->grids()
            ->withCount('positions')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $grids]);
    }

    public function show(Site $site, Grid $grid): JsonResponse
    {
        $this->authorize('view', $site);

        $grid->load('positions');

        return response()->json(['data' => $grid]);
    }

    private const GRID_FIELDS = [
        'name', 'col_tracks', 'row_tracks', 'areas',
        'gap_x', 'gap_y', 'container_width', 'container_padding',
        'min_height', 'align_items', 'justify_items',
        'overflow_x', 'layout_mode', 'background_json', 'full_bleed',
        'breakpoints_json',
    ];

    private function gridValidation(bool $required = false): array
    {
        $r = $required ? 'required' : 'sometimes';
        return [
            'name' => [$r, 'string', 'max:255'],
            'col_tracks' => ['sometimes', 'string', 'max:500'],
            'row_tracks' => ['sometimes', 'string', 'max:500'],
            'areas' => [$r, 'string'],
            'gap_x' => ['sometimes', 'string', 'max:20'],
            'gap_y' => ['sometimes', 'string', 'max:20'],
            'container_width' => ['sometimes', 'string', 'max:30'],
            'container_padding' => ['sometimes', 'string', 'max:50'],
            'min_height' => ['sometimes', 'nullable', 'string', 'max:30'],
            'align_items' => ['sometimes', 'string', 'max:20'],
            'justify_items' => ['sometimes', 'string', 'max:20'],
            'overflow_x' => ['sometimes', 'string', 'max:20'],
            'layout_mode' => ['sometimes', 'string', 'in:default,horizontal-scroll,snap-sections'],
            'background_json' => ['sometimes', 'nullable', 'array'],
            'full_bleed' => ['sometimes', 'boolean'],
            'breakpoints_json' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $request->validate($this->gridValidation(true));

        $data = $request->only(self::GRID_FIELDS);
        $data['site_id'] = $site->id;
        $data['slug'] = Str::slug($data['name']);

        $grid = Grid::create($data);

        return response()->json(['data' => $grid->load('positions')], 201);
    }

    public function update(Request $request, Site $site, Grid $grid): JsonResponse
    {
        $this->authorize('update', $site);
        $request->validate($this->gridValidation(false));

        $grid->update($request->only(self::GRID_FIELDS));

        return response()->json(['data' => $grid->load('positions')]);
    }

    public function destroy(Site $site, Grid $grid): JsonResponse
    {
        $this->authorize('update', $site);

        if ($grid->is_preset) {
            return response()->json(['message' => 'Cannot delete preset grids.'], 422);
        }

        $grid->delete();

        return response()->json(null, 204);
    }

    // ─── POSITIONS ───

    public function syncPositions(Request $request, Site $site, Grid $grid): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.area_name' => ['required', 'string', 'max:50'],
            'positions.*.label' => ['required', 'string', 'max:100'],
            'positions.*.type' => ['required', 'in:canvas,menu,query,fixed,widget,static'],
            'positions.*.config_json' => ['sometimes', 'nullable', 'array'],
            'positions.*.scope' => ['sometimes', 'in:site,page,grid'],
            'positions.*.is_overridable' => ['sometimes', 'boolean'],
            'positions.*.mobile_order' => ['sometimes', 'integer'],
            'positions.*.min_height' => ['sometimes', 'nullable', 'string', 'max:30'],
            'positions.*.align_self' => ['sometimes', 'nullable', 'string', 'max:20'],
            'positions.*.justify_self' => ['sometimes', 'nullable', 'string', 'max:20'],
            'positions.*.max_width' => ['sometimes', 'nullable', 'string', 'max:30'],
            'positions.*.overflow' => ['sometimes', 'nullable', 'string', 'max:20'],
            'positions.*.background_json' => ['sometimes', 'nullable', 'array'],
            'positions.*.padding_json' => ['sometimes', 'nullable', 'array'],
            'positions.*.border_json' => ['sometimes', 'nullable', 'array'],
            'positions.*.shadow' => ['sometimes', 'nullable', 'string', 'max:100'],
            'positions.*.css_class' => ['sometimes', 'nullable', 'string', 'max:200'],
            'positions.*.full_bleed' => ['sometimes', 'boolean'],
        ]);

        // Delete existing positions and re-create
        GridPosition::where('grid_id', $grid->id)->delete();

        foreach ($request->input('positions') as $posData) {
            GridPosition::create([
                'grid_id' => $grid->id,
                ...$posData,
            ]);
        }

        return response()->json(['data' => $grid->load('positions')]);
    }

    // ─── ASSIGNMENTS ───

    public function assignments(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $assignments = GridAssignment::where('site_id', $site->id)
            ->with('grid:id,name,slug')
            ->orderBy('priority')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function storeAssignment(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'grid_id' => ['required', 'uuid', 'exists:grids,id'],
            'assignable_type' => ['required', 'in:page,post,post_type,category,rule,default'],
            'assignable_id' => ['sometimes', 'nullable'],
            'priority' => ['sometimes', 'integer'],
        ]);

        $assignment = GridAssignment::create([
            'site_id' => $site->id,
            ...$request->only(['grid_id', 'assignable_type', 'assignable_id', 'priority']),
        ]);

        return response()->json(['data' => $assignment->load('grid:id,name')], 201);
    }

    public function updateAssignment(Request $request, Site $site, GridAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'grid_id' => ['sometimes', 'uuid', 'exists:grids,id'],
            'priority' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $assignment->update($request->only(['grid_id', 'priority', 'is_active']));

        return response()->json(['data' => $assignment->load('grid:id,name')]);
    }

    public function destroyAssignment(Site $site, GridAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $site);

        if ($assignment->assignable_type === 'default') {
            return response()->json(['message' => 'Cannot delete the default assignment.'], 422);
        }

        $assignment->delete();

        return response()->json(null, 204);
    }

    // ─── POSITION OVERRIDES ───

    public function storeOverride(Request $request, Site $site, GridPosition $position): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'page_id' => ['sometimes', 'uuid'],
            'post_id' => ['sometimes', 'uuid'],
            'content_json' => ['required', 'array'],
        ]);

        $override = PositionOverride::updateOrCreate(
            [
                'grid_position_id' => $position->id,
                'page_id' => $request->input('page_id'),
                'post_id' => $request->input('post_id'),
            ],
            ['content_json' => $request->input('content_json')]
        );

        return response()->json(['data' => $override], 201);
    }

    public function destroyOverride(Site $site, PositionOverride $override): JsonResponse
    {
        $this->authorize('update', $site);

        $override->delete();

        return response()->json(null, 204);
    }

    // ─── SEED PRESETS ───

    public function seedPresets(Site $site, GridPresetSeeder $seeder): JsonResponse
    {
        $this->authorize('update', $site);

        if ($site->grids()->where('is_preset', true)->exists()) {
            return response()->json(['message' => 'Presets already seeded.'], 409);
        }

        $seeder->seed($site);

        return response()->json(['data' => $site->grids()->withCount('positions')->get()], 201);
    }
}
