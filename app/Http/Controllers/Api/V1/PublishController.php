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
