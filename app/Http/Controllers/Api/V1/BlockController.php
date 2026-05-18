<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Blocks\Services\BlockService;
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

    public function indexForPage(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        return response()->json([
            'data' => $this->blockService->getBlockTree($page),
        ]);
    }

    public function syncForPage(SyncBlocksRequest $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $tree = $this->blockService->syncBlocks($page, $request->validated('blocks'));

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

        return response()->json(['data' => $tree]);
    }

    public function indexForPost(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json([
            'data' => $this->blockService->getBlockTree($post),
        ]);
    }

    public function syncForPost(SyncBlocksRequest $request, Site $site, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $tree = $this->blockService->syncBlocks($post, $request->validated('blocks'));

        // Smart auto-publish — rebuild post + its archives
        if ($post->status === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'post_updated', $post->id);
        }

        return response()->json(['data' => $tree]);
    }

    public function indexForTemplate(Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        abort_if($themeTemplate->site_id !== $site->id, 404);
        return response()->json([
            'data' => $this->blockService->getBlockTree($themeTemplate),
        ]);
    }

    public function syncForTemplate(SyncBlocksRequest $request, Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        abort_if($themeTemplate->site_id !== $site->id, 404);
        $tree = $this->blockService->syncBlocks($themeTemplate, $request->validated('blocks'));
        return response()->json(['data' => $tree]);
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'data' => $this->blockRegistry->getAllTypes(),
        ]);
    }
}
