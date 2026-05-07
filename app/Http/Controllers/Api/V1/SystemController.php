<?php

namespace App\Http\Controllers\Api\V1;

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

        // Build in the same request (fast enough for ~1.5MB)
        $this->buildExportZip();

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

    private function buildExportZip(): void
    {
        $zipPath = storage_path('app/cms-export.zip');
        $tmpPath = storage_path('app/tmp/cms-export-build.zip');
        File::ensureDirectoryExists(dirname($tmpPath));

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP');
        }

        $basePath = base_path();
        $include = ['app', 'config', 'database', 'resources', 'routes', 'docs', 'public/admin-assets', 'tests'];
        $rootFiles = ['composer.json', 'composer.lock', 'package.json', 'artisan', '.env.example', 'README.md', 'phpunit.xml'];

        foreach ($rootFiles as $file) {
            $full = $basePath . '/' . $file;
            if (file_exists($full)) {
                $zip->addFile($full, $file);
            }
        }

        foreach ($include as $dir) {
            $dirPath = $basePath . '/' . $dir;
            if (!is_dir($dirPath)) continue;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($basePath) + 1);
                if (preg_match('#(vendor|node_modules|\.git)/#', $relative)) continue;
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        $zip->close();

        // Atomic replace
        rename($tmpPath, $zipPath);
    }
}
