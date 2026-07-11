<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\StylePreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Style Presets (Builder Experience P3). Named style bundles blocks link to.
 * Site-owned presets + shared system presets are listed together; only
 * site-owned ones can be created/edited/deleted. Editing or deleting a preset
 * flags every page/post that links it stale (block→preset 'uses' edges), so the
 * change republishes through the existing staleness engine.
 */
class StylePresetController extends Controller
{
    public function __construct(private StalenessResolver $staleness) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $items = StylePreset::query()
            ->where(fn ($w) => $w->where('site_id', $site->id)->orWhere('is_system', true))
            ->when($request->query('block_type'), fn ($w, $t) => $w->where(fn ($q) => $q->where('block_type', $t)->orWhere('block_type', '*')))
            ->when($request->query('kind'), fn ($w, $k) => $w->where('kind', $k))
            ->orderByDesc('is_system')->orderBy('block_type')->orderBy('sort')->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, Site $site, StylePreset $stylePreset): JsonResponse
    {
        $this->authorize('view', $site);
        abort_unless($stylePreset->is_system || $stylePreset->site_id === $site->id, 404);

        return response()->json(['data' => $stylePreset]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate($this->rules());

        $preset = StylePreset::create($this->fill($data, $site));

        return response()->json(['data' => $preset], 201);
    }

    public function update(Request $request, Site $site, StylePreset $stylePreset): JsonResponse
    {
        $this->authorize('update', $site);
        if ($stylePreset->is_system) {
            return response()->json(['message' => 'System presets cannot be edited.'], 403);
        }
        abort_unless($stylePreset->site_id === $site->id, 404);

        $data = $request->validate($this->rules(partial: true));
        $stylePreset->fill($data)->save();

        // restyle every page that links this preset
        $stale = $this->staleness->markStale($site, 'style_preset', $stylePreset->id, "Preset '{$stylePreset->name}' updated");

        return response()->json(['data' => $stylePreset->fresh(), 'meta' => ['stale' => $stale]]);
    }

    public function destroy(Request $request, Site $site, StylePreset $stylePreset): JsonResponse
    {
        $this->authorize('update', $site);
        if ($stylePreset->is_system) {
            return response()->json(['message' => 'System presets cannot be deleted.'], 403);
        }
        abort_unless($stylePreset->site_id === $site->id, 404);

        $id = $stylePreset->id;
        $name = $stylePreset->name;
        $stylePreset->delete();
        // linked pages lose the preset styling → flag them to republish
        $this->staleness->markStale($site, 'style_preset', $id, "Preset '{$name}' deleted");

        return response()->json(null, 204);
    }

    /** Export the site's presets as one design-system JSON document. */
    public function export(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $presets = StylePreset::where('site_id', $site->id)
            ->orderBy('block_type')->orderBy('sort')->get()
            ->map(fn (StylePreset $p) => [
                'block_type' => $p->block_type, 'kind' => $p->kind, 'group' => $p->group,
                'name' => $p->name, 'style' => $p->style, 'is_default' => $p->is_default, 'sort' => $p->sort,
            ]);

        return response()->json(['data' => ['version' => 1, 'presets' => $presets]]);
    }

    /** Import a design-system JSON document (presets only; validated). */
    public function import(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'presets' => ['required', 'array', 'max:500'],
            'presets.*.name' => ['required', 'string', 'max:100'],
            'presets.*.block_type' => ['required', 'string', 'max:40'],
            'presets.*.kind' => ['required', 'in:element,group'],
            'presets.*.group' => ['sometimes', 'nullable', 'string', 'max:24'],
            'presets.*.style' => ['required', 'array'],
            'presets.*.is_default' => ['sometimes', 'boolean'],
            'presets.*.sort' => ['sometimes', 'integer'],
        ]);

        $created = 0;
        foreach ($validated['presets'] as $p) {
            StylePreset::create($this->fill($p, $site));
            $created++;
        }

        return response()->json(['data' => ['imported' => $created]], 201);
    }

    // ── helpers ──

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$req, 'string', 'max:100'],
            'block_type' => ['sometimes', 'string', 'max:40'],
            'kind' => ['sometimes', 'in:element,group'],
            'group' => ['sometimes', 'nullable', 'in:' . implode(',', StylePreset::GROUPS)],
            'style' => [$req, 'array'],
            'is_default' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer'],
        ];
    }

    /** @return array<string,mixed> */
    private function fill(array $data, Site $site): array
    {
        return [
            'site_id' => $site->id,
            'block_type' => $data['block_type'] ?? '*',
            'kind' => $data['kind'] ?? 'element',
            'group' => $data['group'] ?? null,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) ?: 'preset',
            'style' => $data['style'],
            'is_default' => $data['is_default'] ?? false,
            'sort' => $data['sort'] ?? 0,
            // is_system is non-fillable — always site-owned
        ];
    }
}
