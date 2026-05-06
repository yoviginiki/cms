<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Magazine;
use App\Models\MagazineElement;
use App\Models\MagazinePage;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MagazineController extends Controller
{
    public function index(Request $request, Site $site): JsonResponse
    {
        $query = $site->magazines()->withCount('pages');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $magazines = $query->orderByDesc('updated_at')->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $magazines]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'page_width' => ['sometimes', 'integer', 'min:100', 'max:2000'],
            'page_height' => ['sometimes', 'integer', 'min:100', 'max:3000'],
        ]);

        $magazine = Magazine::create([
            'site_id' => $site->id,
            'title' => $request->input('title'),
            'slug' => Magazine::generateUniqueSlug($request->input('title'), $site->id),
            'description' => $request->input('description'),
            'page_width' => $request->input('page_width', 210),
            'page_height' => $request->input('page_height', 297),
            'status' => 'draft',
        ]);

        // Create first blank page
        $magazine->pages()->create([
            'title' => 'Cover',
            'sort_order' => 0,
            'background_color' => '#ffffff',
        ]);

        return response()->json(['data' => $magazine->load('pages.elements')], 201);
    }

    public function show(Site $site, Magazine $magazine): JsonResponse
    {
        $magazine->load(['pages' => fn($q) => $q->orderBy('sort_order'), 'pages.elements' => fn($q) => $q->orderBy('z_index')]);

        return response()->json(['data' => $magazine]);
    }

    public function update(Request $request, Site $site, Magazine $magazine): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'cover_image' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'page_width' => ['sometimes', 'integer', 'min:100', 'max:2000'],
            'page_height' => ['sometimes', 'integer', 'min:100', 'max:3000'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $data = $request->only(['title', 'slug', 'description', 'cover_image', 'status', 'page_width', 'page_height', 'settings']);

        if (isset($data['slug']) && $data['slug'] !== $magazine->slug) {
            $data['slug'] = Magazine::generateUniqueSlug($data['slug'], $site->id, $magazine->id);
        }
        if (isset($data['status']) && $data['status'] === 'published' && !$magazine->published_at) {
            $data['published_at'] = now();
        }

        $magazine->update($data);

        return response()->json(['data' => $magazine->fresh()]);
    }

    public function destroy(Site $site, Magazine $magazine): JsonResponse
    {
        $this->authorize('update', $site);

        $magazine->delete();
        return response()->json(null, 204);
    }

    /**
     * Bulk save all pages and elements for the visual editor.
     */
    public function savePages(Request $request, Site $site, Magazine $magazine): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'pages' => ['required', 'array'],
            'pages.*.id' => ['sometimes', 'nullable', 'string'],
            'pages.*.title' => ['sometimes', 'nullable', 'string'],
            'pages.*.sort_order' => ['required', 'integer'],
            'pages.*.background_color' => ['sometimes', 'nullable', 'string'],
            'pages.*.background_image' => ['sometimes', 'nullable', 'string'],
            'pages.*.settings' => ['sometimes', 'nullable', 'array'],
            'pages.*.elements' => ['sometimes', 'array'],
            'pages.*.elements.*.type' => ['required', 'string', 'in:text,image,video,hotspot,shape'],
            'pages.*.elements.*.content' => ['sometimes', 'array'],
            'pages.*.elements.*.x' => ['required', 'numeric'],
            'pages.*.elements.*.y' => ['required', 'numeric'],
            'pages.*.elements.*.width' => ['required', 'numeric'],
            'pages.*.elements.*.height' => ['required', 'numeric'],
        ]);

        DB::transaction(function () use ($request, $magazine) {
            $existingPageIds = $magazine->pages()->pluck('id')->toArray();
            $incomingPageIds = [];

            foreach ($request->input('pages') as $pageData) {
                $pageId = $pageData['id'] ?? null;

                if ($pageId && in_array($pageId, $existingPageIds)) {
                    // Update existing page
                    $page = MagazinePage::find($pageId);
                    $page->update([
                        'title' => $pageData['title'] ?? null,
                        'sort_order' => $pageData['sort_order'],
                        'background_color' => $pageData['background_color'] ?? null,
                        'background_image' => $pageData['background_image'] ?? null,
                        'settings' => $pageData['settings'] ?? null,
                    ]);
                    $incomingPageIds[] = $pageId;
                } else {
                    // Create new page
                    $page = $magazine->pages()->create([
                        'title' => $pageData['title'] ?? null,
                        'sort_order' => $pageData['sort_order'],
                        'background_color' => $pageData['background_color'] ?? '#ffffff',
                        'background_image' => $pageData['background_image'] ?? null,
                        'settings' => $pageData['settings'] ?? null,
                    ]);
                    $incomingPageIds[] = $page->id;
                }

                // Sync elements for this page
                $page->elements()->delete();
                foreach ($pageData['elements'] ?? [] as $elData) {
                    $page->elements()->create([
                        'type' => $elData['type'],
                        'content' => $elData['content'] ?? [],
                        'x' => $elData['x'],
                        'y' => $elData['y'],
                        'width' => $elData['width'],
                        'height' => $elData['height'],
                        'rotation' => $elData['rotation'] ?? 0,
                        'z_index' => $elData['z_index'] ?? 0,
                        'style' => $elData['style'] ?? [],
                    ]);
                }
            }

            // Delete removed pages
            MagazinePage::where('magazine_id', $magazine->id)
                ->whereNotIn('id', $incomingPageIds)
                ->delete();
        });

        return response()->json([
            'data' => $magazine->fresh()->load(['pages' => fn($q) => $q->orderBy('sort_order'), 'pages.elements']),
        ]);
    }
}
