<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pages\Services\PageService;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePageRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        private PageService $pageService,
        private AutoPublishService $autoPublish,
        private StalenessResolver $staleness,
    ) {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        if ($request->boolean('tree')) {
            return response()->json([
                'data' => $this->pageService->getPageTree($site),
            ]);
        }

        $query = $site->pages()->orderBy('sort_order');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($editorMode = $request->query('editor_mode')) {
            $query->where('editor_mode', $editorMode);
        }

        return PageResource::collection($query->paginate($request->integer('per_page', 15)))->response();
    }

    public function show(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $page->load(['blocks' => fn($q) => $q->whereNull('parent_block_id')->orderBy('order')->with('children')]);

        return (new PageResource($page))->response();
    }

    public function store(CreatePageRequest $request, Site $site): JsonResponse
    {
        $this->authorize('create', [Page::class, $site]);

        $page = $this->pageService->createPage($request->validated(), $site);

        if ($page->status === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'page_updated', $page->id);
        }

        return (new PageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePageRequest $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $oldStatus = $page->status;
        $oldSlug = $page->slug;
        $page = $this->pageService->updatePage($page, $request->validated());

        // Slug change: pages/posts linking here still contain the old URL
        if ($page->slug !== $oldSlug && $oldStatus === 'published') {
            $this->staleness->markStaleForLinkTargets(
                $site, 'page', $page->id,
                "Internal link target renamed: /{$oldSlug} → /{$page->slug}",
            );
        }

        if ($page->status === 'published' || $oldStatus === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'page_updated', $page->id);
        }

        return (new PageResource($page))->response();
    }

    public function destroy(Site $site, Page $page): JsonResponse
    {
        $this->authorize('delete', $page);

        $wasPublished = $page->status === 'published';
        $page->delete();

        if ($wasPublished) {
            // Referring pages now contain a dead link
            $this->staleness->markStaleForLinkTargets(
                $site, 'page', $page->id,
                "Linked page '{$page->title}' deleted",
            );
            $this->autoPublish->triggerIfEnabled($site, null, 'page_updated', $page->id);
        }

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request, Site $site): JsonResponse
    {
        $this->authorize('reorder', [Page::class, $site]);

        $this->pageService->reorderPages($site, $request->validated('items'));

        return response()->json(['message' => 'Pages reordered.']);
    }

    public function duplicate(Site $site, Page $page): JsonResponse
    {
        $this->authorize('create', [Page::class, $site]);

        $newPage = $page->replicate(['id', 'slug', 'created_at', 'updated_at']);
        $newPage->title = $page->title . ' (Copy)';
        $newPage->slug = $page->slug . '-copy-' . substr(md5(now()->timestamp), 0, 4);
        $newPage->status = 'draft';
        $newPage->save();

        // Copy all blocks with remapped IDs
        $this->duplicateBlocks($page, $newPage);

        return (new PageResource($newPage))->response()->setStatusCode(201);
    }

    private function duplicateBlocks($source, $target): void
    {
        $blocks = \App\Models\Block::where('blockable_type', $source->getMorphClass())
            ->where('blockable_id', $source->getKey())
            ->orderBy('order')
            ->get();

        $idMap = [];
        foreach ($blocks as $block) {
            $newId = \Illuminate\Support\Str::uuid()->toString();
            $idMap[$block->id] = $newId;
        }

        foreach ($blocks as $block) {
            \App\Models\Block::create([
                'id' => $idMap[$block->id],
                'blockable_type' => $target->getMorphClass(),
                'blockable_id' => $target->getKey(),
                'parent_block_id' => $block->parent_block_id ? ($idMap[$block->parent_block_id] ?? null) : null,
                'type' => $block->type,
                'data' => $block->data,
                'order' => $block->order,
                'style' => $block->style,
            ]);
        }
    }
}
