<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __construct(private BlockService $blockService) {}

    public function indexForPage(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $versions = PageVersion::where('page_id', $page->id)
            ->orderByDesc('version_number')
            ->with('publishedByUser:id,name')
            ->get(['id', 'version_number', 'published_by', 'published_at', 'created_at']);

        return response()->json(['data' => $versions]);
    }

    public function indexForPost(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $versions = PageVersion::where('post_id', $post->id)
            ->orderByDesc('version_number')
            ->with('publishedByUser:id,name')
            ->get(['id', 'version_number', 'published_by', 'published_at', 'created_at']);

        return response()->json(['data' => $versions]);
    }

    public function showForPage(Site $site, Page $page, PageVersion $version): JsonResponse
    {
        $this->authorize('view', $page);

        return response()->json(['data' => $version]);
    }

    public function showForPost(Site $site, Post $post, PageVersion $version): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json(['data' => $version]);
    }

    public function restoreForPage(Site $site, Page $page, PageVersion $version): JsonResponse
    {
        $this->authorize('update', $page);

        // P4: snapshot the CURRENT state first, so a restore is itself undoable.
        $this->snapshotCurrent($page, 'page_id');

        // Restore blocks from snapshot
        if (!empty($version->blocks_snapshot)) {
            $this->blockService->syncBlocks($page, $version->blocks_snapshot);
        }

        // Restore SEO snapshot
        if (!empty($version->seo_snapshot)) {
            $page->update(['seo_meta' => $version->seo_snapshot]);
        }

        return response()->json(['data' => ['message' => 'Version restored', 'version' => $version->version_number]]);
    }

    public function restoreForPost(Site $site, Post $post, PageVersion $version): JsonResponse
    {
        $this->authorize('update', $post);

        $this->snapshotCurrent($post, 'post_id');

        if (!empty($version->blocks_snapshot)) {
            $this->blockService->syncBlocks($post, $version->blocks_snapshot);
        }

        if (!empty($version->seo_snapshot)) {
            $post->update(['seo_meta' => $version->seo_snapshot]);
        }

        return response()->json(['data' => ['message' => 'Version restored', 'version' => $version->version_number]]);
    }

    /** Snapshot the content's current blocks + SEO as a new version (undo point). */
    private function snapshotCurrent(Page|Post $content, string $fkColumn): void
    {
        $last = PageVersion::where($fkColumn, $content->id)->orderByDesc('version_number')->first();
        PageVersion::create([
            $fkColumn => $content->id,
            'blocks_snapshot' => $this->blockService->getBlockTree($content),
            'seo_snapshot' => $content->seo_meta ?? [],
            'published_by' => auth()->id(),
            'published_at' => now(),
            'version_number' => ($last?->version_number ?? 0) + 1,
        ]);
    }
}
