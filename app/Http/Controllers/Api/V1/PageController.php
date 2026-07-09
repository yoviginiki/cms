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
        $oldPublishedPath = $oldStatus === 'published'
            ? \App\Domain\Publishing\Services\LocalePaths::pagePath($site, $page)
            : null;
        $page = $this->pageService->updatePage($page, $request->validated());

        // Static delete-half: remove the old published file when the content
        // was unpublished or its path changed (slug/category/locale)
        try {
            if ($oldPublishedPath !== null) {
                $newPath = \App\Domain\Publishing\Services\LocalePaths::pagePath($site, $page);
                if ($page->status !== 'published' || $newPath !== $oldPublishedPath) {
                    app(\App\Domain\Publishing\Services\StaticCleaner::class)->removePath($site, $oldPublishedPath);
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('StaticCleaner (update) failed: ' . $e->getMessage());
        }



        // Slug change: pages/posts linking here still contain the old URL
        if ($page->slug !== $oldSlug && $oldStatus === 'published') {
            $this->staleness->markStaleForLinkTargets(
                $site, 'page', $page->id,
                "Internal link target renamed: /{$oldSlug} → /{$page->slug}",
            );

            // FIX-B7b: write a 301 from the old URL to the new one so the
            // renamed page's old address doesn't 404 (delete-half above removed
            // the old file).
            if ($oldPublishedPath !== null) {
                $oldUrl = '/' . preg_replace('~index\.html$~', '', $oldPublishedPath);
                $newUrl = \App\Domain\Publishing\Services\LocalePaths::urlPath($site, $page);
                if ($oldUrl !== $newUrl) {
                    \App\Models\Redirect::firstOrCreate(
                        ['site_id' => $site->id, 'source_path' => $oldUrl],
                        ['target_url' => $newUrl, 'status_code' => 301],
                    );
                }
            }
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
        if ($wasPublished) {
            try {
                app(\App\Domain\Publishing\Services\StaticCleaner::class)->removeContent($site, $page);
            } catch (\Throwable $e) {
                logger()->warning('StaticCleaner (delete) failed: ' . $e->getMessage());
            }
        }
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


    public function translate(Site $site, Page $page, \Illuminate\Http\Request $request): JsonResponse
    {
        $this->authorize('create', [Page::class, $site]);
        $locale = (string) $request->validate(['locale' => ['required', 'string', 'max:10']])['locale'];

        $translation = app(\App\Domain\Publishing\Services\TranslationService::class)
            ->translate($page, $locale, $site);

        return (new PageResource($translation))->response()->setStatusCode(201);
    }

    public function translations(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $rows = [];
        foreach (app(\App\Domain\Publishing\Services\TranslationService::class)->siblings($page, $site) as $locale => $sibling) {
            $rows[] = [
                'locale' => $locale,
                'id' => $sibling->id,
                'title' => $sibling->title,
                'slug' => $sibling->slug,
                'status' => $sibling->status,
            ];
        }

        return response()->json(['data' => $rows]);
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

    /**
     * Duplicate a magazine-mode page as a new canvas-mode page: convert its
     * mag pages/elements into a section-stacked canvas block tree. The original
     * is untouched; the copy is a draft.
     */
    public function duplicateAsCanvas(Site $site, Page $page): JsonResponse
    {
        $this->authorize('create', [Page::class, $site]);

        $converter = app(\App\Domain\Magazine\Services\MagazineToCanvasConverter::class);
        $tree = $converter->convert($page);
        $width = $converter->designWidth($page);

        $newPage = $page->replicate(['id', 'slug', 'created_at', 'updated_at']);
        $newPage->title = $page->title . ' (Canvas)';
        $newPage->slug = $page->slug . '-canvas-' . substr(md5((string) now()->timestamp), 0, 4);
        $newPage->status = 'draft';
        $newPage->editor_mode = 'canvas';
        $newPage->seo_meta = array_merge($page->seo_meta ?? [], [
            'canvas' => ['page_type' => 'website', 'width' => $width],
        ]);
        $newPage->save();

        app(\App\Domain\Blocks\Services\BlockService::class)->syncBlocks($newPage, $tree);

        return (new PageResource($newPage->fresh()))->response()->setStatusCode(201);
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
