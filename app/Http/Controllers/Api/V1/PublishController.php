<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    public function __construct(private PublishOrchestrator $orchestrator)
    {
    }

    public function publish(Request $request, Site $site): JsonResponse
    {
        $this->authorize('publish', $site);

        $request->validate([
            'type' => ['sometimes', 'in:full,partial'],
        ]);

        try {
            $deployment = $this->orchestrator->publish(
                $site,
                $request->user(),
                $request->input('type', 'partial'),
            );

            return response()->json(['data' => $deployment], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Clear all published static files (wipe the public site).
     */
    public function clear(Request $request, Site $site): JsonResponse
    {
        $this->authorize('publish', $site);

        $publicPath = config('publishing.public_path');
        if (!$publicPath || !is_dir($publicPath)) {
            return response()->json(['message' => 'No published content to clear.']);
        }

        // Remove all generated content but keep vendor/ and other non-CMS dirs
        $keep = ['vendor', 'assets', '.htaccess'];
        foreach (scandir($publicPath) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (in_array($entry, $keep)) continue;
            $path = $publicPath . '/' . $entry;
            if (is_dir($path)) {
                \Illuminate\Support\Facades\File::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return response()->json(['message' => 'Published content cleared.']);
    }

    public function status(Site $site, Deployment $deployment): JsonResponse
    {
        return response()->json(['data' => $deployment]);
    }

    public function history(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $deployments = Deployment::where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($deployments);
    }

    public function rollback(Request $request, Site $site, Deployment $deployment): JsonResponse
    {
        $this->authorize('update', $site);

        if ($deployment->status !== 'live') {
            return response()->json(['message' => 'Can only rollback to a live deployment.'], 422);
        }

        $newDeployment = $this->orchestrator->rollback($site, $deployment, $request->user());

        return response()->json(['data' => $newDeployment], 201);
    }
}
