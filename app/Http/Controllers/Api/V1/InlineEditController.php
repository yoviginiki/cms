<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\InlineEdit\Services\InlineEditService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inline edit — save / draft / export API (Phase 3).
 *
 * Additive namespace. Writes go to the LIVE `blocks` rows owned by the page
 * (the editable working copy) exactly like the Page Editor — never to published
 * state, which is only produced by the separate publish/deploy path. Publishing
 * stays an explicit, separate act.
 */
class InlineEditController extends Controller
{
    public function __construct(
        private BlockService $blockService,
        private InlineEditService $inline,
        private BuildPageService $buildService,
    ) {
    }

    /** Open an edit session: current lock token + a per-block content hash. */
    public function session(Site $site, Page $page): JsonResponse
    {
        $this->authorize('inlineEdit', $page);

        $blocks = $this->pageBlocks($page)->map(fn (Block $b) => [
            'block' => $b->id,
            'hash' => $this->inline->blockHash($b),
        ])->values();

        return response()->json([
            'session_id' => (string) Str::uuid(),
            'version' => $this->blockService->blocksVersion($page),
            'blocks' => $blocks,
        ]);
    }

    /** Batch-patch fields on this page's blocks. Draft only; publishing is separate. */
    public function patchBlocks(Request $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('inlineEdit', $page);

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
        if ($expected !== null && $expected !== $this->blockService->blocksVersion($page)) {
            abort(409, 'These blocks were modified by someone else since you loaded them. Reload to get the latest version.');
        }

        $byBlock = collect($data['patches'])->groupBy('block');

        $updated = DB::transaction(function () use ($page, $byBlock) {
            $result = [];

            foreach ($byBlock as $blockId => $patches) {
                $block = Block::where('id', $blockId)
                    ->where('blockable_type', $page->getMorphClass())
                    ->where('blockable_id', $page->getKey())
                    ->first();

                if (!$block) {
                    abort(404, "Block {$blockId} not found on this page.");
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

            // Keep entity-reference edges in step with the edited blocks — same
            // synchronous recompute the full-tree sync does; never fail the save
            // over reference bookkeeping.
            try {
                app(ReferenceRecorder::class)->recompute($page);
            } catch (\Throwable $e) {
                logger()->warning("inline entity_references recompute failed for page {$page->id}: {$e->getMessage()}");
            }

            // Real content change — stamp content_modified_at without firing
            // model events (matches BlockController::syncForPage).
            Page::whereKey($page->id)->toBase()->update(['content_modified_at' => now()]);

            return $result;
        });

        return response()->json([
            'version' => $this->blockService->blocksVersion($page),
            'blocks' => $updated,
        ]);
    }

    /** Materialize a draft page_version snapshot from the current live blocks. */
    public function draft(Request $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('inlineEdit', $page);

        $last = PageVersion::where('page_id', $page->id)->orderByDesc('version_number')->first();

        $version = PageVersion::create([
            'page_id' => $page->id,
            'blocks_snapshot' => $this->blockService->getBlockTree($page),
            'seo_snapshot' => $page->seo_meta ?? [],
            'published_by' => $request->user()?->id,
            'published_at' => now(),
            'version_number' => ($last?->version_number ?? 0) + 1,
        ]);

        return response()->json([
            'version_id' => $version->id,
            'version_number' => $version->version_number,
        ]);
    }

    /** Export the current draft as json (block payload) or html (publish render). */
    public function export(Request $request, Site $site, Page $page): Response|JsonResponse
    {
        $this->authorize('inlineEdit', $page);

        $format = $request->query('format', 'json');

        if ($format === 'json') {
            return response()
                ->json(['blocks' => $this->blockService->getBlockTree($page)])
                ->header('Content-Disposition', 'attachment; filename="' . $page->slug . '.json"');
        }

        if ($format === 'html') {
            $site->load('theme');

            // Rendered through RenderMode::Publish (the default) — byte-for-byte
            // what a visitor gets, NOT the edit render.
            $html = $this->buildService->build($page, $site->theme, $site);

            return response($html, 200)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . $page->slug . '.html"');
        }

        abort(422, "Unknown export format '{$format}'. Use json or html.");
    }

    /** @return \Illuminate\Support\Collection<int,Block> */
    private function pageBlocks(Page $page)
    {
        return Block::where('blockable_type', $page->getMorphClass())
            ->where('blockable_id', $page->getKey())
            ->orderBy('order')
            ->get();
    }
}
