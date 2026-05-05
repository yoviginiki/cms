<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\Jobs\ExecuteImportJob;
use App\Domain\Import\Services\WordPressImporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteImportRequest;
use App\Http\Requests\UploadImportRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function __construct(private WordPressImporter $importer)
    {
    }

    /**
     * Upload a WXR file for import.
     */
    public function upload(UploadImportRequest $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $file = $request->file('file');
        $importId = Str::uuid()->toString();

        // Store in temp location
        $tempPath = storage_path("app/imports/{$importId}.xml");
        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($file->getRealPath(), $tempPath);

        // Store import metadata
        Cache::put("import:{$importId}", [
            'status' => 'uploaded',
            'message' => 'File uploaded, ready for preview',
            'site_id' => $site->id,
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'xml_path' => $tempPath,
            'uploaded_at' => now()->toISOString(),
        ], now()->addHours(2));

        return response()->json([
            'data' => [
                'import_id' => $importId,
                'filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ],
        ], 201);
    }

    /**
     * Preview what will be imported without executing.
     */
    public function preview(Site $site, string $importId): JsonResponse
    {
        $this->authorize('update', $site);

        $meta = Cache::get("import:{$importId}");
        if (!$meta || ($meta['site_id'] ?? '') !== $site->id) {
            return response()->json(['message' => 'Import not found.'], 404);
        }

        $xmlPath = $meta['xml_path'] ?? '';
        if (!file_exists($xmlPath)) {
            return response()->json(['message' => 'Import file not found. Please re-upload.'], 404);
        }

        $preview = $this->importer->preview($xmlPath);

        return response()->json(['data' => $preview]);
    }

    /**
     * Execute the import as a queued job.
     */
    public function execute(ExecuteImportRequest $request, Site $site, string $importId): JsonResponse
    {
        $this->authorize('update', $site);

        $meta = Cache::get("import:{$importId}");
        if (!$meta || ($meta['site_id'] ?? '') !== $site->id) {
            return response()->json(['message' => 'Import not found.'], 404);
        }

        $xmlPath = $meta['xml_path'] ?? '';
        if (!file_exists($xmlPath)) {
            return response()->json(['message' => 'Import file not found. Please re-upload.'], 404);
        }

        // Don't allow re-execution of already running/completed imports
        $status = $meta['status'] ?? '';
        if (in_array($status, ['running', 'completed'])) {
            return response()->json([
                'message' => "Import is already {$status}.",
            ], 409);
        }

        // Update status to queued
        Cache::put("import:{$importId}", array_merge($meta, [
            'status' => 'queued',
            'message' => 'Import job queued, waiting for worker...',
            'step' => 'queued',
            'progress' => 0,
        ]), now()->addHours(2));

        // Dispatch the job
        ExecuteImportJob::dispatch(
            $site->id,
            $importId,
            $xmlPath,
            $request->validated(),
            $site->tenant_id,
        );

        return response()->json([
            'data' => [
                'import_id' => $importId,
                'status' => 'queued',
                'message' => 'Import job has been queued.',
            ],
        ], 202);
    }

    /**
     * Check import progress.
     */
    public function status(Site $site, string $importId): JsonResponse
    {
        $this->authorize('view', $site);

        $meta = Cache::get("import:{$importId}");
        if (!$meta || ($meta['site_id'] ?? '') !== $site->id) {
            return response()->json(['message' => 'Import not found.'], 404);
        }

        return response()->json([
            'data' => [
                'import_id' => $importId,
                'status' => $meta['status'] ?? 'unknown',
                'message' => $meta['message'] ?? '',
                'step' => $meta['step'] ?? null,
                'progress' => $meta['progress'] ?? 0,
                'counts' => $meta['counts'] ?? null,
                'result' => $meta['result'] ?? null,
                'error' => $meta['error'] ?? null,
            ],
        ]);
    }
}
