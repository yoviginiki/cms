<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\GlobalSections\Services\GlobalSectionService;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\BlockTemplate;
use App\Models\EntityReference;
use App\Models\GlobalSection;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global Section LIBRARY entities (Builder Experience P2): list/create/promote/
 * rename/delete + block-tree sync + publish. Pages embed them via the global_ref
 * block; publish fires the generic staleness engine (embedding pages go stale →
 * republish). Delete is protected by inbound entity_references. Mirrors
 * SliderController.
 */
class GlobalSectionController extends Controller
{
    public function __construct(
        private GlobalSectionService $sections,
        private ReferenceUsageService $usage,
        private BlockService $blocks,
    ) {}

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $sections = GlobalSection::where('site_id', $site->id)->orderBy('name')->get();

        $counts = EntityReference::where('site_id', $site->id)
            ->where('target_type', 'global_section')
            ->whereIn('target_id', $sections->pluck('id'))
            ->selectRaw('target_id, count(*) as c')
            ->groupBy('target_id')
            ->pluck('c', 'target_id');

        return response()->json(['data' => $sections->map(fn (GlobalSection $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'status' => $s->status,
            'published_at' => $s->published_at,
            'updated_at' => $s->updated_at,
            'used_on' => (int) ($counts[$s->id] ?? 0),
        ])]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $section = $this->sections->create($site, $validated['name']);

        return response()->json(['data' => $section], 201);
    }

    /** Promote a Library item into a new global section (a detached copy). */
    public function promote(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'block_template_id' => ['required', 'uuid'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $item = BlockTemplate::query()
            ->where(fn ($w) => $w->where('site_id', $site->id)->orWhere('is_system', true))
            ->findOrFail($validated['block_template_id']);

        $section = $this->sections->promoteFromLibrary($site, $item, $validated['name'] ?? null);

        return response()->json(['data' => $section], 201);
    }

    public function show(Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('view', $site);
        abort_unless($globalSection->site_id === $site->id, 404);

        return response()->json(['data' => [
            'section' => $globalSection,
            'blocks' => $this->blocks->getBlockTree($globalSection),
            'usage' => $this->usage->usage($site, 'global_section', $globalSection->id),
        ]]);
    }

    public function update(Request $request, Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($globalSection->site_id === $site->id, 404);
        $validated = $request->validate(['name' => ['sometimes', 'string', 'max:255']]);

        $globalSection->update($validated);

        return response()->json(['data' => $globalSection->fresh()]);
    }

    /** Sync the full block tree (any blocks — a global section is a free chunk). */
    public function syncBlocks(Request $request, Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($globalSection->site_id === $site->id, 404);
        $request->validate(['blocks' => ['required', 'array']]);

        $tree = $this->sections->syncBlocks($globalSection, $request->input('blocks'));

        return response()->json(['data' => $tree]);
    }

    /** Publish: flips status + flags dependents via the staleness engine. */
    public function publish(Request $request, Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('publish', $site);
        abort_unless($globalSection->site_id === $site->id, 404);

        $affected = $this->sections->publish($globalSection);

        return response()->json(['data' => $globalSection->fresh(), 'meta' => ['stale' => $affected]]);
    }

    public function unpublish(Request $request, Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('publish', $site);
        abort_unless($globalSection->site_id === $site->id, 404);

        $affected = $this->sections->unpublish($globalSection);

        return response()->json(['data' => $globalSection->fresh(), 'meta' => ['stale' => $affected]]);
    }

    public function destroy(Request $request, Site $site, GlobalSection $globalSection): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($globalSection->site_id === $site->id, 404);

        $usage = $this->usage->usage($site, 'global_section', $globalSection->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Global section '{$globalSection->name}' is still used. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $name = $globalSection->name;
        $id = $globalSection->id;
        Block::where('blockable_type', 'global_section')->where('blockable_id', $id)->delete();
        EntityReference::forSource('global_section', $id)->delete();
        $globalSection->delete();

        if ($usage['count'] > 0) {
            app(StalenessResolver::class)->markStale($site, 'global_section', $id, "Global section '{$name}' deleted (was in use)");
        }

        return response()->json(null, 204);
    }
}
