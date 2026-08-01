<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\InlineEdit\Services\InlineEditService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Domain\References\Services\ReferenceRecorder;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inline edit — save / draft / export API (Phase 3).
 *
 * Additive namespace. Writes go to the LIVE `blocks` rows owned by the page or
 * post (the editable working copy) exactly like the Page Editor — never to
 * published state, which is only produced by the separate publish/deploy path.
 * Publishing stays an explicit, separate act. Pages and posts share the same
 * logic; only route binding and the draft snapshot key differ.
 */
class InlineEditController extends Controller
{
    public function __construct(
        private BlockService $blockService,
        private InlineEditService $inline,
        private BuildPageService $buildService,
        private PublishOrchestrator $orchestrator,
    ) {
    }

    // ---- page endpoints ----------------------------------------------------

    public function session(Site $site, Page $page): JsonResponse
    {
        return $this->openSession($page);
    }

    public function patchBlocks(Request $request, Site $site, Page $page): JsonResponse
    {
        return $this->patchContentBlocks($request, $page);
    }

    public function draft(Request $request, Site $site, Page $page): JsonResponse
    {
        return $this->makeDraft($request, $page);
    }

    public function publish(Request $request, Site $site, Page $page): JsonResponse
    {
        return $this->publishContent($request, $site, $page);
    }

    public function export(Request $request, Site $site, Page $page): Response|JsonResponse
    {
        return $this->exportContent($request, $site, $page);
    }

    // ---- post endpoints ----------------------------------------------------

    public function sessionPost(Site $site, Post $post): JsonResponse
    {
        return $this->openSession($post);
    }

    public function patchBlocksPost(Request $request, Site $site, Post $post): JsonResponse
    {
        return $this->patchContentBlocks($request, $post);
    }

    public function draftPost(Request $request, Site $site, Post $post): JsonResponse
    {
        return $this->makeDraft($request, $post);
    }

    public function publishPost(Request $request, Site $site, Post $post): JsonResponse
    {
        return $this->publishContent($request, $site, $post);
    }

    public function exportPost(Request $request, Site $site, Post $post): Response|JsonResponse
    {
        return $this->exportContent($request, $site, $post);
    }

    // ---- shared implementation (Page|Post) ---------------------------------

    /** Open an edit session: current lock token + a per-block content hash. */
    private function openSession(Page|Post $content): JsonResponse
    {
        $this->authorize('inlineEdit', $content);

        $blocks = $this->contentBlocks($content)->map(fn (Block $b) => [
            'block' => $b->id,
            'hash' => $this->inline->blockHash($b),
        ])->values();

        return response()->json([
            'session_id' => (string) Str::uuid(),
            'version' => $this->blockService->blocksVersion($content),
            'blocks' => $blocks,
        ]);
    }

    /** Batch-patch fields on this content's blocks. Draft only; publish is separate. */
    private function patchContentBlocks(Request $request, Page|Post $content): JsonResponse
    {
        $this->authorize('inlineEdit', $content);

        $data = $request->validate([
            'expected_version' => ['nullable', 'string'],
            'patches' => ['required', 'array', 'min:1'],
            'patches.*.block' => ['required', 'string'],
            'patches.*.field' => ['required', 'string'],
            'patches.*.value' => ['present'],
            'patches.*.block_hash' => ['nullable', 'string'],
        ]);

        // Whole-tree optimistic lock (reuses the FIX-C11a blocksVersion token).
        $expected = $data['expected_version'] ?? null;
        if ($expected !== null && $expected !== $this->blockService->blocksVersion($content)) {
            abort(409, 'These blocks were modified by someone else since you loaded them. Reload to get the latest version.');
        }

        $byBlock = collect($data['patches'])->groupBy('block');

        $updated = DB::transaction(function () use ($content, $byBlock) {
            $result = [];

            foreach ($byBlock as $blockId => $patches) {
                $block = Block::where('id', $blockId)
                    ->where('blockable_type', $content->getMorphClass())
                    ->where('blockable_id', $content->getKey())
                    ->first();

                if (!$block) {
                    abort(404, "Block {$blockId} not found on this content.");
                }

                // Shared-entity blocks are read-only here (403).
                $this->inline->assertPatchable($block);

                // Per-block optimistic lock via the session hash (409).
                $this->inline->assertHashMatches($block, $patches->first()['block_hash'] ?? null);

                // Validate + sanitize every field, then persist.
                $block->data = $this->inline->applyPatches($block, $patches->map(fn ($p) => [
                    'field' => $p['field'],
                    'value' => $p['value'],
                ])->all());
                $block->save();

                $result[] = [
                    'block' => $block->id,
                    'hash' => $this->inline->blockHash($block),
                    'data' => $block->data,
                ];
            }

            // Keep entity-reference edges in step with the edited blocks.
            try {
                app(ReferenceRecorder::class)->recompute($content);
            } catch (\Throwable $e) {
                logger()->warning("inline entity_references recompute failed for {$content->getMorphClass()} {$content->id}: {$e->getMessage()}");
            }

            // Real content change — stamp content_modified_at without firing
            // model events (matches BlockController::syncForPage/Post).
            DB::table($content->getTable())
                ->where('id', $content->getKey())
                ->update(['content_modified_at' => now()]);

            return $result;
        });

        return response()->json([
            'version' => $this->blockService->blocksVersion($content),
            'blocks' => $updated,
        ]);
    }

    /** Materialize a draft page_version snapshot from the current live blocks. */
    private function makeDraft(Request $request, Page|Post $content): JsonResponse
    {
        $this->authorize('inlineEdit', $content);

        // page_versions is keyed by exactly one of page_id / post_id (CHECK).
        $key = $content instanceof Post ? 'post_id' : 'page_id';

        $last = PageVersion::where($key, $content->id)->orderByDesc('version_number')->first();

        $version = PageVersion::create([
            $key => $content->id,
            'blocks_snapshot' => $this->blockService->getBlockTree($content),
            'seo_snapshot' => $content->seo_meta ?? [],
            'published_by' => $request->user()?->id,
            'published_at' => now(),
            'version_number' => ($last?->version_number ?? 0) + 1,
        ]);

        return response()->json([
            'version_id' => $version->id,
            'version_number' => $version->version_number,
        ]);
    }

    /**
     * Publish from inline edit mode: promote a draft to published and rebuild the
     * site via the existing orchestrator. Requires inlinePublish (admin+).
     */
    private function publishContent(Request $request, Site $site, Page|Post $content): JsonResponse
    {
        $this->authorize('inlinePublish', $content);

        if ($content->status !== 'published') {
            $content->status = 'published';
            $content->published_at = $content->published_at ?? now();
            $content->save();
        }

        try {
            $deployment = $this->orchestrator->publish($site, $request->user(), 'partial');
        } catch (\RuntimeException $e) {
            // A deployment is already in progress.
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'status' => $content->status,
            'deployment' => $deployment,
        ], 201);
    }

    /** Export the current draft as json (block payload) or html (publish render). */
    private function exportContent(Request $request, Site $site, Page|Post $content): Response|JsonResponse
    {
        $this->authorize('inlineEdit', $content);

        $format = $request->query('format', 'json');

        if ($format === 'json') {
            return response()
                ->json(['blocks' => $this->blockService->getBlockTree($content)])
                ->header('Content-Disposition', 'attachment; filename="' . $content->slug . '.json"');
        }

        if ($format === 'html') {
            $site->load('theme');

            // Rendered through RenderMode::Publish (the default) — byte-for-byte
            // what a visitor gets, NOT the edit render.
            $html = $this->buildService->build($content, $site->theme, $site);

            return response($html, 200)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . $content->slug . '.html"');
        }

        abort(422, "Unknown export format '{$format}'. Use json or html.");
    }

    /** @return \Illuminate\Support\Collection<int,Block> */
    private function contentBlocks(Page|Post $content)
    {
        return Block::where('blockable_type', $content->getMorphClass())
            ->where('blockable_id', $content->getKey())
            ->orderBy('order')
            ->get();
    }
}
