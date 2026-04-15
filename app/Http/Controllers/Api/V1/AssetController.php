<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Services\AssetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAssetRequest;
use App\Http\Resources\V1\AssetResource;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private AssetService $assetService)
    {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = $site->assets()->orderByDesc('created_at');

        if ($mime = $request->query('mime_type')) {
            if ($mime === 'images') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($mime === 'documents') {
                $query->where('mime_type', 'not like', 'image/%');
            } else {
                $query->where('mime_type', $mime);
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

    public function destroy(Site $site, Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        $this->assetService->delete($asset);

        return response()->json(null, 204);
    }
}
