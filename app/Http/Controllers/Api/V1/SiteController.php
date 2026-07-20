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
            ->orderBy('name')
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

        // Capture homepage config before the update so we can detect a change
        // (FIX-B7a): changing the homepage must rebuild the site root, else the
        // old index.html stays live and stale indefinitely.
        $before = collect($site->settings ?? [])->only(['homepage_id', 'homepage_type', 'homepage_grid_id'])->all();

        $site = $this->siteService->updateSite($site, $request->validated());

        $after = collect($site->settings ?? [])->only(['homepage_id', 'homepage_type', 'homepage_grid_id'])->all();
        if ($before !== $after) {
            try {
                $resolver = app(\App\Domain\References\Services\StalenessResolver::class);
                // Flag both the old and new homepage pages so the root rebuilds.
                foreach (array_filter([$before['homepage_id'] ?? null, $after['homepage_id'] ?? null]) as $pageId) {
                    if ($page = \App\Models\Page::where('site_id', $site->id)->find($pageId)) {
                        $resolver->markStaleForLinkTargets($site, 'page', $page->id, 'Homepage changed');
                        $page->update(['needs_republish' => true, 'needs_republish_reason' => 'Homepage changed']);
                    }
                }
                $resolver->markSiteStale($site, 'Homepage configuration changed');
            } catch (\Throwable $e) {
                logger()->warning("Homepage staleness flag failed for site {$site->id}: {$e->getMessage()}");
            }
        }

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
