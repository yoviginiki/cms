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
     * Download the entire CMS platform as a ZIP for migration.
     * Serves a pre-built ZIP if available, otherwise builds one on-the-fly.
     */
    public function exportCms(Request $request): BinaryFileResponse|JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $prebuilt = storage_path('app/cms-export.zip');
        if (file_exists($prebuilt)) {
            return response()->download($prebuilt, 'cms-platform-' . date('Y-m-d') . '.zip', [
                'Content-Type' => 'application/zip',
            ]);
        }

        // Build on the fly
        $zipPath = storage_path('app/tmp/cms-export-' . now()->format('Ymd-His') . '.zip');
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Failed to create ZIP'], 500);
        }

        $basePath = base_path();
        $include = ['app', 'config', 'database', 'resources', 'routes', 'docs', 'public/admin-assets', 'tests'];
        $rootFiles = ['composer.json', 'composer.lock', 'package.json', 'artisan', '.env.example', 'README.md', 'phpunit.xml'];

        // Add root files
        foreach ($rootFiles as $file) {
            $full = $basePath . '/' . $file;
            if (file_exists($full)) {
                $zip->addFile($full, $file);
            }
        }

        // Add directories
        foreach ($include as $dir) {
            $dirPath = $basePath . '/' . $dir;
            if (!is_dir($dirPath)) continue;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($basePath) + 1);
                // Skip vendor, node_modules, .git, storage
                if (preg_match('#(vendor|node_modules|\.git)/#', $relative)) continue;
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        $zip->close();

        return response()->download($zipPath, 'cms-platform-' . date('Y-m-d') . '.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
