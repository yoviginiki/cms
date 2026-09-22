<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\System\Services\CmsExportService;
use App\Domain\System\Services\UpdateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemController extends Controller
{
    public function __construct(private UpdateService $updateService) {}

    public function checkUpdate(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $update = $this->updateService->checkForUpdates();

        return response()->json([
            'data' => [
                'current_version' => $this->updateService->getCurrentVersion(),
                'update_available' => $update !== null,
                'update' => $update,
            ],
        ]);
    }

    public function applyUpdate(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'version' => ['required', 'string'],
            'download_url' => ['required', 'url'],
            'checksum' => ['required', 'string'],
        ]);

        try {
            $zipPath = $this->updateService->downloadUpdate(
                $request->input('version'),
                $request->input('download_url'),
                $request->input('checksum')
            );

            $result = $this->updateService->applyUpdate($zipPath);

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger CMS export ZIP generation.
     */
    public function generateExport(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $lockFile = storage_path('app/cms-export.lock');
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 120) {
            return response()->json(['data' => ['status' => 'generating']], 202);
        }

        // Mark as generating
        File::put($lockFile, (string) time());

        // Build in the same request (a few seconds for the whole source tree)
        app(CmsExportService::class)->build();

        // Remove lock
        @unlink($lockFile);

        return response()->json(['data' => ['status' => 'ready']]);
    }

    /**
     * Check export status and file info.
     */
    public function exportStatus(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $zipPath = storage_path('app/cms-export.zip');
        $lockFile = storage_path('app/cms-export.lock');
        $generating = file_exists($lockFile) && (time() - filemtime($lockFile)) < 120;

        if ($generating) {
            return response()->json(['data' => ['status' => 'generating']]);
        }

        if (file_exists($zipPath)) {
            return response()->json(['data' => [
                'status' => 'ready',
                'size' => filesize($zipPath),
                'generated_at' => date('c', filemtime($zipPath)),
            ]]);
        }

        return response()->json(['data' => ['status' => 'none']]);
    }

    /**
     * Download the CMS export ZIP.
     */
    public function downloadExport(Request $request): BinaryFileResponse|JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $zipPath = storage_path('app/cms-export.zip');
        if (!file_exists($zipPath)) {
            return response()->json(['message' => 'No export available. Generate one first.'], 404);
        }

        return response()->download($zipPath, 'cms-platform-' . date('Y-m-d') . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
