<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Library\Services\LibraryItemSanitizer;
use App\Jobs\Library\GenerateLibraryThumbnailJob;
use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The Library (Builder Experience P1). Reusable sections / rows /
 * block-compositions / modules, stored in `block_templates`. Site-owned items
 * plus shared system items (site_id = NULL, RLS-readable by all tenants) are
 * listed together; only site-owned items can be created/edited/deleted.
 */
class BlockTemplateController extends Controller
{
    /** GET — list library items, with optional search/filter. */
    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $q = trim((string) $request->query('q', ''));
        $kind = $request->query('kind');
        $category = $request->query('category');
        $tag = $request->query('tag');

        $items = BlockTemplate::query()
            // RLS already scopes to this tenant's sites + system rows; constrain
            // to THIS site's items plus shared system items.
            ->where(fn ($w) => $w->where('site_id', $site->id)->orWhere('is_system', true))
            ->when($kind, fn ($w) => $w->where('kind', $kind))
            ->when($category, fn ($w) => $w->where('category', $category))
            ->when($tag, fn ($w) => $w->whereJsonContains('tags', $tag))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('name', 'ilike', "%{$q}%")
                ->orWhere('description', 'ilike', "%{$q}%")))
            ->orderByDesc('is_system')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    /** GET — a single item (used by export/download). */
    public function show(Request $request, Site $site, BlockTemplate $template): JsonResponse
    {
        $this->authorize('view', $site);
        abort_unless($this->visibleTo($template, $site), 404);

        return response()->json(['data' => $template]);
    }

    /** POST — save a selection from the editor to the Library. */
    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate($this->rules());

        $item = BlockTemplate::create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($site, $validated['name']),
            'category' => $validated['category'] ?? 'custom',
            'kind' => $validated['kind'] ?? $this->inferKind($validated['blocks_data']),
            'tags' => $validated['tags'] ?? [],
            'description' => $validated['description'] ?? null,
            'blocks_data' => $validated['blocks_data'],
            // is_system is non-fillable — always a site-owned item.
        ]);

        GenerateLibraryThumbnailJob::dispatch($item->id, $site->id, $site->tenant_id);

        return response()->json(['data' => $item], 201);
    }

    /** PATCH — rename / recategorize / retag (metadata only, not the block tree). */
    public function update(Request $request, Site $site, BlockTemplate $template): JsonResponse
    {
        $this->authorize('update', $site);
        if ($template->is_system) {
            return response()->json(['message' => 'System library items cannot be edited.'], 403);
        }
        abort_unless($template->site_id === $site->id, 404); // never edit another site's item

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'kind' => ['sometimes', 'nullable', 'in:' . implode(',', BlockTemplate::KINDS)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $template->fill($validated)->save();

        return response()->json(['data' => $template]);
    }

    /** POST — import a single library item from validated + sanitized JSON. */
    public function import(Request $request, Site $site, LibraryItemSanitizer $sanitizer): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate($this->rules());

        try {
            $blocks = $sanitizer->sanitizeTree($validated['blocks_data']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $item = BlockTemplate::create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($site, $validated['name']),
            'category' => $validated['category'] ?? 'imported',
            'kind' => $validated['kind'] ?? $this->inferKind($blocks),
            'tags' => $validated['tags'] ?? [],
            'description' => $validated['description'] ?? null,
            'blocks_data' => $blocks,
        ]);

        GenerateLibraryThumbnailJob::dispatch($item->id, $site->id, $site->tenant_id);

        return response()->json(['data' => $item], 201);
    }

    public function destroy(Site $site, BlockTemplate $template): JsonResponse
    {
        $this->authorize('update', $site);
        if ($template->is_system) {
            return response()->json(['message' => 'System library items cannot be deleted.'], 403);
        }
        abort_unless($template->site_id === $site->id, 404);

        $template->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ── helpers ──

    /** @return array<string,mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'kind' => ['sometimes', 'nullable', 'in:' . implode(',', BlockTemplate::KINDS)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'blocks_data' => ['required', 'array', 'min:1'],
        ];
    }

    private function visibleTo(BlockTemplate $t, Site $site): bool
    {
        return $t->is_system || $t->site_id === $site->id;
    }

    /** A section wraps rows/columns; a single block is a module; else a composition. */
    private function inferKind(array $blocks): string
    {
        if (count($blocks) === 1) {
            $level = $blocks[0]['level'] ?? ($blocks[0]['data']['__level'] ?? null);
            if (in_array($level, ['section', 'row'], true)) return $level;
            return isset($blocks[0]['children']) && $blocks[0]['children'] ? 'block-composition' : 'module';
        }
        return 'block-composition';
    }

    private function uniqueSlug(Site $site, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $n = 2;
        while (BlockTemplate::where('site_id', $site->id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }
        return $slug;
    }
}
