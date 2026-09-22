<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\DeployTargetResolver;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Services\ActivityLogService;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * Clear this site's published static output (F01, audit 2026-09-22).
     *
     * Separate, admin-only permission and an activity-log entry: this is
     * destructive. Scope is resolved by DeployTargetResolver — only output
     * OWNED by this site is removed; the shared public root, other sites'
     * folders/builds and anything behind a symlink are never touched.
     */
    public function clear(Request $request, Site $site, DeployTargetResolver $targets): JsonResponse
    {
        $this->authorize('clearPublished', $site);

        $result = $targets->clearLiveOutput($site);

        app(ActivityLogService::class)->log(
            'site.published_output_cleared',
            $site->id,
            'site',
            $site->id,
            ['removed' => $result['removed'], 'target' => $result['target']],
        );

        if ($result['removed'] === 0) {
            return response()->json(['message' => 'No published content to clear.', 'data' => $result]);
        }

        return response()->json(['message' => 'Published content cleared.', 'data' => $result]);
    }

    public function status(Site $site, Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $site);
        abort_unless($deployment->site_id === $site->id, 404);

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

    /**
     * Download the latest build as a ZIP file.
     */
    public function downloadZip(Site $site): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $site);

        // Find latest successful deployment with an artifact
        $deployment = Deployment::where('site_id', $site->id)
            ->where('status', 'live')
            ->whereNotNull('artifact_path')
            ->orderByDesc('completed_at')
            ->first();

        if (!$deployment || !is_dir($deployment->artifact_path)) {
            return response()->json(['message' => 'No build available. Publish the site first.'], 404);
        }

        $zipName = $site->slug . '-' . now()->format('Y-m-d-His') . '.zip';
        $zipPath = storage_path("app/tmp/{$zipName}");
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Failed to create ZIP file.'], 500);
        }

        $basePath = realpath($deployment->artifact_path);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), strlen($basePath) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function rollback(Request $request, Site $site, Deployment $deployment): JsonResponse
    {
        $this->authorize('update', $site);
        // The rollback target must be one of THIS site's deployments (F07).
        abort_unless($deployment->site_id === $site->id, 404);

        if ($deployment->status !== 'live') {
            return response()->json(['message' => 'Can only rollback to a live deployment.'], 422);
        }

        $newDeployment = $this->orchestrator->rollback($site, $deployment, $request->user());

        return response()->json(['data' => $newDeployment], 201);
    }
}
