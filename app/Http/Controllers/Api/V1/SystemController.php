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

    /**
     * Apply a release package (F02). OFF unless the installation operator
     * enables web-applied updates; then limited to the OWNER of the operator
     * tenant, an allow-listed https source, a strict version token and an
     * Ed25519 signature over the checksum — all checked before any download.
     */
    public function applyUpdate(Request $request): JsonResponse
    {
        $user = $request->user();
        $operatorTenant = (string) config('cms.updates.operator_tenant_id', '');

        if (!config('cms.updates.web_apply_enabled')
            || !$user
            || !$user->isOwner()
            || $operatorTenant === ''
            || (string) $user->tenant_id !== $operatorTenant
            || (string) config('cms.updates.public_key', '') === '') {
            return response()->json([
                'message' => 'System updates are applied by the installation operator, not through the tenant API.',
            ], 403);
        }

        $svc = $this->updateService;
        $request->validate([
            'version' => ['required', 'string', 'max:64', fn ($a, $v, $fail) => $svc->isValidVersion((string) $v) ?: $fail('The version is not a valid release version.')],
            'download_url' => ['required', 'string', 'max:2048', fn ($a, $v, $fail) => $svc->isAllowedSource((string) $v) ?: $fail('The download URL must be an https URL on the configured update server.')],
            'checksum' => ['required', 'string', 'max:128', fn ($a, $v, $fail) => $svc->isValidChecksum((string) $v) ?: $fail('The checksum must be sha256:<hex>.')],
            'signature' => ['required', 'string', 'max:256'],
        ]);
        if (!$svc->verifySignature($request->input('checksum'), $request->input('signature'))) {
            return response()->json([
                'message' => 'The release signature could not be verified.',
                'errors' => ['signature' => ['The release signature does not verify with the configured release key.']],
            ], 422);
        }

        try {
            $zipPath = $svc->downloadUpdate(
                $request->input('version'),
                $request->input('download_url'),
                $request->input('checksum')
            );

            $result = $svc->applyUpdate($zipPath);

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
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
