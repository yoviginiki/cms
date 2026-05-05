<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\System\Services\UpdateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
