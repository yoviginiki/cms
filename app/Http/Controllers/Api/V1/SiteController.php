<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sites\Services\SiteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(private SiteService $siteService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Site::class);

        $sites = Site::withCount(['pages', 'posts'])
            ->with('theme')
            ->paginate($request->integer('per_page', 15));

        return SiteResource::collection($sites)->response();
    }

    public function show(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $site->load('theme')->loadCount(['pages', 'posts']);

        return (new SiteResource($site))->response();
    }

    public function store(CreateSiteRequest $request): JsonResponse
    {
        $this->authorize('create', Site::class);

        $site = $this->siteService->createSite(
            $request->validated(),
            $request->user()->tenant
        );

        $site->loadCount(['pages', 'posts']);

        return (new SiteResource($site))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $site = $this->siteService->updateSite($site, $request->validated());
        $site->loadCount(['pages', 'posts']);

        return (new SiteResource($site))->response();
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        $this->siteService->deleteSite($site);

        return response()->json(null, 204);
    }
}
