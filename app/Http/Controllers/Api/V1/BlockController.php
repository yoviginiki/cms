<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Blocks\Services\BlockService;
use App\Domain\Blocks\Support\TrustedHtml;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SyncBlocksRequest;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Post;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Http\JsonResponse;

class BlockController extends Controller
{
    public function __construct(
        private BlockService $blockService,
        private BlockRegistry $blockRegistry,
        private AutoPublishService $autoPublish,
    ) {
    }

    /**
     * Optimistic concurrency (F13). The interactive API must send the
     * `expected_version` it captured on load; the compare happens inside the
     * write transaction (BlockService::bumpRevision). A programmatic client
     * that really wants last-write-wins says so with `overwrite: true`.
     */
    private function expectedVersion(SyncBlocksRequest $request): ?string
    {
        $expected = $request->input('expected_version');
        if ($expected === null && !$request->boolean('overwrite')) {
            abort(422, 'expected_version is required (send the version returned by the blocks GET, or overwrite: true to force).');
        }

        return $expected === null ? null : (string) $expected;
    }

    public function indexForPage(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        return response()->json([
            'data' => $this->blockService->getBlockTree($page),
            'version' => $this->blockService->blocksVersion($page),
        ]);
    }

    public function syncForPage(SyncBlocksRequest $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('update', $page);
        $expected = $this->expectedVersion($request);

        // Trusted-HTML capability (F05): editors may not introduce/change
        // html-embed blocks or raw_html through this path.
        TrustedHtml::assertMayWriteTree($request->user(), $request->validated('blocks'), $this->blockService->getBlockTree($page));
        if ($request->has('raw_html')) {
            TrustedHtml::assertMayWriteRaw($request->user(), $request->input('raw_html'), $page->raw_html);
        }

        $tree = $this->blockService->syncBlocks($page, $request->validated('blocks'), $expected);

        // Real content change — stamp content_modified_at without firing model
        // events (updated_at stays untouched by design; see F4 migration note).
        Page::whereKey($page->id)->toBase()->update(['content_modified_at' => now()]);

        // Save raw HTML if provided
        if ($request->has('raw_html')) {
            $page->raw_html = $request->input('raw_html');
            $page->save();
        }

        // Create draft snapshot every 5th save (based on version count)
        if ($request->boolean('create_snapshot')) {
            $lastVersion = PageVersion::where('page_id', $page->id)->orderByDesc('version_number')->first();
            PageVersion::create([
                'page_id' => $page->id,
                'blocks_snapshot' => $request->validated('blocks'),
                'seo_snapshot' => $page->seo_meta ?? [],
                'published_by' => $request->user()?->id,
                'published_at' => now(),
                'version_number' => ($lastVersion?->version_number ?? 0) + 1,
            ]);
        }

        // Smart auto-publish — only rebuild this page
        if ($page->status === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'page_blocks', $page->id);
        }

        return response()->json(['data' => $tree, 'version' => $this->blockService->blocksVersion($page)]);
    }

    public function indexForPost(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json([
            'data' => $this->blockService->getBlockTree($post),
            'version' => $this->blockService->blocksVersion($post),
        ]);
    }

    public function syncForPost(SyncBlocksRequest $request, Site $site, Post $post): JsonResponse
    {
        $this->authorize('update', $post);
        $expected = $this->expectedVersion($request);
        TrustedHtml::assertMayWriteTree($request->user(), $request->validated('blocks'), $this->blockService->getBlockTree($post));

        $tree = $this->blockService->syncBlocks($post, $request->validated('blocks'), $expected);

        // Real content change — stamp content_modified_at (F4)
        Post::whereKey($post->id)->toBase()->update(['content_modified_at' => now()]);

        // Smart auto-publish — rebuild post + its archives
        if ($post->status === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'post_updated', $post->id);
        }

        return response()->json(['data' => $tree, 'version' => $this->blockService->blocksVersion($post)]);
    }

    public function indexForTemplate(Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        abort_if($themeTemplate->site_id !== $site->id, 404);
        $this->authorize('view', $themeTemplate);
        return response()->json([
            'data' => $this->blockService->getBlockTree($themeTemplate),
            'version' => $this->blockService->blocksVersion($themeTemplate),
        ]);
    }

    public function syncForTemplate(SyncBlocksRequest $request, Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        abort_if($themeTemplate->site_id !== $site->id, 404);
        // F07: template writes carry the same role gate as the template itself
        // (admin+); the site-ownership check above is not authorization.
        $this->authorize('update', $themeTemplate);
        $expected = $this->expectedVersion($request);
        TrustedHtml::assertMayWriteTree($request->user(), $request->validated('blocks'), $this->blockService->getBlockTree($themeTemplate));
        $tree = $this->blockService->syncBlocks($themeTemplate, $request->validated('blocks'), $expected);

        // Auto-publish — regenerate all pages/posts using this template
        $this->autoPublish->triggerIfEnabled($site, $request->user(), 'template_updated', $themeTemplate->id);

        return response()->json(['data' => $tree, 'version' => $this->blockService->blocksVersion($themeTemplate)]);
    }

    public function indexForGlobalSection(Site $site, \App\Models\GlobalSection $globalSection): JsonResponse
    {
        abort_if($globalSection->site_id !== $site->id, 404);
        $this->authorize('view', $site);

        return response()->json([
            'data' => $this->blockService->getBlockTree($globalSection),
            'version' => $this->blockService->blocksVersion($globalSection),
        ]);
    }

    /**
     * Same protocol as pages/templates (validated tree, TrustedHtml gate,
     * expected_version). A published section's save republishes its
     * dependents — for grid header/footer areas that is the whole site.
     */
    public function syncForGlobalSection(SyncBlocksRequest $request, Site $site, \App\Models\GlobalSection $globalSection): JsonResponse
    {
        abort_if($globalSection->site_id !== $site->id, 404);
        $this->authorize('update', $site);
        $expected = $this->expectedVersion($request);
        TrustedHtml::assertMayWriteTree($request->user(), $request->validated('blocks'), $this->blockService->getBlockTree($globalSection));
        $tree = $this->blockService->syncBlocks($globalSection, $request->validated('blocks'), $expected);

        app(\App\Domain\GlobalSections\Services\GlobalSectionService::class)->contentChanged($globalSection->fresh(), $request->user());

        return response()->json(['data' => $tree, 'version' => $this->blockService->blocksVersion($globalSection)]);
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'data' => $this->blockRegistry->getAllTypes(),
        ]);
    }
}
