<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\Sliders\Services\SliderService;
use App\Http\Controllers\Controller;
use App\Models\EntityReference;
use App\Models\Site;
use App\Models\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slider LIBRARY entities: list/create/rename/duplicate/delete + block-tree
 * sync + publish. Publish fires the generic staleness engine; delete is
 * protected by inbound entity_references (force flag to override).
 */
class SliderController extends Controller
{
    public function __construct(
        private SliderService $sliders,
        private ReferenceUsageService $usage,
        private BlockService $blocks,
    ) {
    }

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $sliders = Slider::where('site_id', $site->id)->orderBy('name')->get();

        // used-on counts in one query (inverse embeds edges)
        $counts = EntityReference::where('site_id', $site->id)
            ->where('target_type', 'slider')
            ->whereIn('target_id', $sliders->pluck('id'))
            ->selectRaw('target_id, count(*) as c')
            ->groupBy('target_id')
            ->pluck('c', 'target_id');

        return response()->json(['data' => $sliders->map(fn (Slider $s) => [
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

        $slider = $this->sliders->create($site, $validated['name']);

        return response()->json(['data' => $slider], 201);
    }

    public function show(Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('view', $site);
        abort_unless($slider->site_id === $site->id, 404);

        return response()->json(['data' => [
            'slider' => $slider,
            'blocks' => $this->blocks->getBlockTree($slider),
            'usage' => $this->usage->usage($site, 'slider', $slider->id),
        ]]);
    }

    public function update(Request $request, Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($slider->site_id === $site->id, 404);
        $validated = $request->validate(['name' => ['sometimes', 'string', 'max:255']]);

        $slider->update($validated);

        return response()->json(['data' => $slider->fresh()]);
    }

    /** Sync the full block tree (slider root -> slides -> layers). */
    public function syncBlocks(Request $request, Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($slider->site_id === $site->id, 404);
        $request->validate(['blocks' => ['required', 'array', 'max:1']]);

        $tree = $this->sliders->syncBlocks($slider, $request->input('blocks'));

        return response()->json(['data' => $tree]);
    }

    /** Publish: flips status + flags dependents via the staleness engine. */
    public function publish(Request $request, Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('publish', $site);
        abort_unless($slider->site_id === $site->id, 404);

        $affected = $this->sliders->publish($slider);

        return response()->json(['data' => $slider->fresh(), 'meta' => ['stale' => $affected]]);
    }

    public function duplicate(Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($slider->site_id === $site->id, 404);

        $copy = $this->sliders->create($site, $slider->name . ' (Copy)');
        $tree = $this->blocks->getBlockTree($slider);
        if ($tree !== []) {
            // strip ids so the copy mints fresh block UUIDs
            $stripIds = function (array $nodes) use (&$stripIds) {
                return array_map(function (array $n) use ($stripIds) {
                    unset($n['id'], $n['preset_id']);
                    $n['children'] = $stripIds($n['children'] ?? []);

                    return $n;
                }, $nodes);
            };
            $this->sliders->syncBlocks($copy, $stripIds($tree));
        }

        return response()->json(['data' => $copy->fresh()], 201);
    }

    public function destroy(Request $request, Site $site, Slider $slider): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($slider->site_id === $site->id, 404);

        // Generic delete protection: block while pages still embed it
        $usage = $this->usage->usage($site, 'slider', $slider->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Slider '{$slider->name}' is still used. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $name = $slider->name;
        $id = $slider->id;
        // remove the block tree + the slider's own outbound edges, then the entity
        \App\Models\Block::where('blockable_type', 'slider')->where('blockable_id', $id)->delete();
        EntityReference::forSource('slider', $id)->delete();
        $slider->delete();

        if ($usage['count'] > 0) {
            app(\App\Domain\References\Services\StalenessResolver::class)
                ->markStale($site, 'slider', $id, "Slider '{$name}' deleted (was in use)");
        }

        return response()->json(null, 204);
    }
}
