<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
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

        $records = \App\Models\Record::where('site_id', $site->id)
            ->where('needs_republish', true)
            ->get(['id', 'collection_id', 'title', 'slug', 'status', 'needs_republish_reason', 'updated_at']);

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
            'records' => $records,
            'site_stale' => $siteStale,
            'count' => $pages->count() + $posts->count() + $records->count() + ($siteStale ? 1 : 0),
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
            'record_ids' => ['sometimes', 'array'],
            'record_ids.*' => ['uuid'],
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
            $recordIds = \App\Models\Record::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
        } else {
            // Only accept ids that are actually flagged and belong to this site
            $pageIds = Page::where('site_id', $site->id)->where('needs_republish', true)
                ->whereIn('id', $validated['page_ids'] ?? [])->pluck('id')->all();
            $postIds = Post::where('site_id', $site->id)->where('needs_republish', true)
                ->whereIn('id', $validated['post_ids'] ?? [])->pluck('id')->all();
            $recordIds = \App\Models\Record::where('site_id', $site->id)->where('needs_republish', true)
                ->whereIn('id', $validated['record_ids'] ?? [])->pluck('id')->all();
        }

        if ($pageIds === [] && $postIds === [] && $recordIds === []) {
            return response()->json(['message' => 'No stale pages selected.'], 422);
        }

        // Same guard as PublishOrchestrator: no concurrent builds per site
        try {
            $deployment = app(\App\Domain\Publishing\Services\DeploymentGate::class)->open($site, 'stale_batch', $request->user(), [
                'targets' => ['pages' => $pageIds, 'posts' => $postIds, 'records' => $recordIds],
                'pages_total' => count($pageIds) + count($postIds) + count($recordIds),
            ], function (Deployment $deployment) {
                if (config('queue.default') !== 'sync') {
                    RepublishStaleJob::dispatch($deployment);
                } else {
                    RepublishStaleJob::dispatchSync($deployment);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $deployment->fresh()], 201);
    }

    public function promote(Request $request, Site $site, Deployment $deployment): JsonResponse
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

        if (app(\App\Domain\Publishing\Services\DeploymentGate::class)->active($site)) {
            return response()->json(['message' => 'A deployment is already in progress for this site.'], 409);
        }

        // Promotion writes into the live docroot, which for custom-domain sites
        // is outside the web pool's open_basedir — run it on the builds worker.
        // F15 checks (site lock, live generation) run again inside the job.
        $metadata = $deployment->metadata ?? [];
        unset($metadata['promote_error']);
        $deployment->update(['metadata' => array_merge($metadata, ['current_step' => 'promoting'])]);
        \App\Domain\Publishing\Jobs\PromoteStagedBatchJob::dispatch($deployment);

        return response()->json(['data' => [
            'deployment' => $deployment->fresh(),
            'queued' => true,
            'promoted' => count($deployment->metadata['built'] ?? []),
        ]], 202);
    }
}
