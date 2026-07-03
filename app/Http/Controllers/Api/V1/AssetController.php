<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Services\AssetService;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAssetRequest;
use App\Http\Resources\V1\AssetResource;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private AssetService $assetService,
        private ReferenceUsageService $usage,
        private StalenessResolver $staleness,
    ) {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = $site->assets()->orderByDesc('created_at');

        $typeFilter = $request->query('type') ?? $request->query('mime_type');
        if ($typeFilter) {
            if ($typeFilter === 'image' || $typeFilter === 'images') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($typeFilter === 'document' || $typeFilter === 'documents') {
                $query->where('mime_type', 'not like', 'image/%')
                      ->where('mime_type', 'not like', 'video/%')
                      ->where('mime_type', 'not like', 'audio/%');
            } elseif ($typeFilter === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            } elseif ($typeFilter === 'audio') {
                $query->where('mime_type', 'like', 'audio/%');
            } else {
                $query->where('mime_type', $typeFilter);
            }
        }

        return AssetResource::collection($query->paginate($request->integer('per_page', 30)))->response();
    }

    public function show(Site $site, Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return (new AssetResource($asset))->response();
    }

    public function store(UploadAssetRequest $request, Site $site): JsonResponse
    {
        $this->authorize('upload', [Asset::class, $site]);

        $asset = $this->assetService->upload($site, $request->file('file'));

        if ($altText = $request->validated('alt_text')) {
            $asset->update(['alt_text' => $altText]);
        }

        return (new AssetResource($asset))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Site $site, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $asset->update($validated);

        return response()->json(['data' => $asset->fresh()]);
    }

    public function destroy(Request $request, Site $site, Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        // Delete protection: block deletion while content still uses the
        // asset, unless the caller explicitly forces it
        $usage = $this->usage->usage($site, 'asset', $asset->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Asset '{$asset->original_name}' is still in use. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $assetName = $asset->original_name;
        $assetId = $asset->id;
        $this->assetService->delete($asset);

        if ($usage['count'] > 0) {
            $this->staleness->markStale($site, 'asset', $assetId, "Asset '{$assetName}' deleted (was in use)");
        }

        return response()->json(null, 204);
    }
}
