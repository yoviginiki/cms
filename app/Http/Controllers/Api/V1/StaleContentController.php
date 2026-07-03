<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\DeployService;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stale-content workflow: list flagged pages/posts, rebuild a selection into
 * a STAGED batch, and promote the staged batch to live on explicit human
 * confirmation. Nothing here auto-publishes.
 */
class StaleContentController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $pages = Page::where('site_id', $site->id)
            ->where('needs_republish', true)
            ->get(['id', 'title', 'slug', 'status', 'needs_republish_reason', 'updated_at']);

        $posts = Post::where('site_id', $site->id)
            ->where('needs_republish', true)
            ->get(['id', 'title', 'slug', 'status', 'needs_republish_reason', 'updated_at']);

        $siteStale = ($site->settings ?? [])['stale'] ?? null;

        // Latest staged batch awaiting promotion, if any
        $staged = Deployment::where('site_id', $site->id)
            ->where('type', 'stale_batch')
            ->whereIn('status', ['queued', 'building', 'staged'])
            ->latest('created_at')
            ->first();

        return response()->json(['data' => [
            'pages' => $pages,
            'posts' => $posts,
            'site_stale' => $siteStale,
            'count' => $pages->count() + $posts->count() + ($siteStale ? 1 : 0),
            'staged_batch' => $staged,
        ]]);
    }

    public function republish(Request $request, Site $site): JsonResponse
    {
        $this->authorize('publish', $site);

        $validated = $request->validate([
            'page_ids' => ['sometimes', 'array'],
            'page_ids.*' => ['uuid'],
            'post_ids' => ['sometimes', 'array'],
            'post_ids.*' => ['uuid'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $method = ($site->settings ?? [])['deploy_method'] ?? 'local';
        if ($method !== 'local') {
            return response()->json([
                'message' => "Stale-batch republish is not available for the '{$method}' deploy method — run a full publish instead.",
            ], 422);
        }

        if ($validated['all'] ?? false) {
            $pageIds = Page::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
            $postIds = Post::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
        } else {
            // Only accept ids that are actually flagged and belong to this site
            $pageIds = Page::where('site_id', $site->id)->where('needs_republish', true)
                ->whereIn('id', $validated['page_ids'] ?? [])->pluck('id')->all();
            $postIds = Post::where('site_id', $site->id)->where('needs_republish', true)
                ->whereIn('id', $validated['post_ids'] ?? [])->pluck('id')->all();
        }

        if ($pageIds === [] && $postIds === []) {
            return response()->json(['message' => 'No stale pages selected.'], 422);
        }

        // Same guard as PublishOrchestrator: no concurrent builds per site
        $active = Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->exists();
        if ($active) {
            return response()->json(['message' => 'A deployment is already in progress for this site.'], 409);
        }

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $request->user()->id,
            'metadata' => [
                'current_step' => 'queued',
                'targets' => ['pages' => $pageIds, 'posts' => $postIds],
                'pages_total' => count($pageIds) + count($postIds),
                'pages_built' => 0,
            ],
        ]);

        if (config('queue.default') !== 'sync') {
            RepublishStaleJob::dispatch($deployment);
        } else {
            RepublishStaleJob::dispatchSync($deployment);
        }

        return response()->json(['data' => $deployment->fresh()], 201);
    }

    public function promote(Request $request, Site $site, Deployment $deployment, DeployService $deployService): JsonResponse
    {
        $this->authorize('publish', $site);

        if ($deployment->site_id !== $site->id || $deployment->type !== 'stale_batch') {
            return response()->json(['message' => 'Not a stale-batch deployment of this site.'], 404);
        }
        if ($deployment->status !== 'staged') {
            return response()->json(['message' => "Deployment is '{$deployment->status}', not staged."], 409);
        }

        $stagingPath = $deployment->artifact_path;
        if (!$stagingPath || !is_dir($stagingPath)) {
            return response()->json([
                'message' => 'Staged build no longer exists (cleaned by a later publish). Re-run the stale republish.',
            ], 410);
        }

        $active = Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->exists();
        if ($active) {
            return response()->json(['message' => 'A deployment is already in progress for this site.'], 409);
        }

        try {
            $deployService->deployPartial($deployment, $stagingPath);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Clear flags ONLY for successfully built sources
        $built = collect($deployment->metadata['built'] ?? []);
        $builtPageIds = $built->where('type', 'page')->pluck('id')->all();
        $builtPostIds = $built->where('type', 'post')->pluck('id')->all();
        if ($builtPageIds !== []) {
            Page::whereIn('id', $builtPageIds)
                ->update(['needs_republish' => false, 'needs_republish_reason' => null]);
        }
        if ($builtPostIds !== []) {
            Post::whereIn('id', $builtPostIds)
                ->update(['needs_republish' => false, 'needs_republish_reason' => null]);
        }

        $deployment->update([
            'status' => 'live',
            'completed_at' => now(),
            'metadata' => array_merge($deployment->metadata ?? [], ['current_step' => 'live']),
        ]);

        return response()->json(['data' => [
            'deployment' => $deployment->fresh(),
            'promoted' => $built->count(),
            'failed' => $deployment->metadata['failed'] ?? [],
        ]]);
    }
}
