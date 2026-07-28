<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Services\CategorySchemaResolver;
use App\Domain\Collections\Services\CollectionCategoryService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\CollectionCategoryNode;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The category tree for one collection: nested tree read, node CRUD,
 * move/reorder, and a node's effective (merged) schema. All mutations go
 * through CollectionCategoryService so depth/cycle/slug rules stay enforced.
 */
class CollectionCategoryController extends Controller
{
    public function __construct(
        private CollectionCategoryService $service,
        private CategorySchemaResolver $resolver,
        private StalenessResolver $staleness,
    ) {
    }

    /**
     * Flag the collection stale so a republish rebuilds its archive, category
     * pages, category images and the client-side SEARCH INDEX — otherwise a
     * category rename/image/description change would leave them outdated. A full
     * publish rebuilds them regardless; this also flags embedding pages.
     */
    private function markCollectionStale(Site $site, ContentCollection $collection): void
    {
        try {
            $this->staleness->markStale($site, 'collection', $collection->id, 'category_changed');
        } catch (\Throwable $e) {
            // never fail a category mutation over staleness bookkeeping
        }
    }

    public function index(Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection);

        return response()->json(['data' => $this->service->tree($collection)]);
    }

    public function store(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $node = $this->service->create($collection, $site, $request->all());
        $this->markCollectionStale($site, $collection);

        return response()->json(['data' => $this->service->serialize($node, 0)], 201);
    }

    public function update(Request $request, Site $site, ContentCollection $collection, CollectionCategoryNode $node): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $node);

        $node = $this->service->update($node, $site, $request->all());
        $this->markCollectionStale($site, $collection);

        return response()->json(['data' => $this->service->serialize($node)]);
    }

    public function move(Request $request, Site $site, ContentCollection $collection, CollectionCategoryNode $node): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $node);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'uuid'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $node = $this->service->move($node, $validated['parent_id'] ?? null, $validated['sort_order'] ?? null);
        $this->markCollectionStale($site, $collection);

        return response()->json(['data' => $this->service->serialize($node)]);
    }

    public function destroy(Request $request, Site $site, ContentCollection $collection, CollectionCategoryNode $node): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $node);

        $mode = $request->query('mode') === 'cascade' ? 'cascade' : 'reparent';
        $result = $this->service->delete($node, $mode);
        $this->markCollectionStale($site, $collection);

        return response()->json(['data' => $result, 'message' => 'Category deleted.']);
    }

    /** The node's effective (merged) schema — used by the record editor. */
    public function effectiveSchema(Site $site, ContentCollection $collection, CollectionCategoryNode $node): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection, $node);

        return response()->json(['data' => [
            'node_id' => $node->id,
            'title_field' => $collection->titleField(),
            'slug_source' => $collection->slugSource(),
            'fields' => $this->resolver->effectiveFields($collection, $node->id),
            'chain' => array_map(
                fn ($n) => ['id' => $n->id, 'name' => $n->name],
                $this->resolver->ancestorChain($collection, $node->id),
            ),
        ]]);
    }

    private function assertOnSite(Site $site, ContentCollection $collection, ?CollectionCategoryNode $node = null): void
    {
        abort_if($collection->site_id !== $site->id, 404);
        abort_if($node && $node->collection_id !== $collection->id, 404);
    }
}
