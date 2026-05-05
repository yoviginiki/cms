<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sites\Services\SiteCloneService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCloneController extends Controller
{
    public function __construct(private SiteCloneService $cloneService) {}

    public function clone(Request $request, Site $site): JsonResponse
    {
        $this->authorize('create', Site::class);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $newSite = $this->cloneService->clone(
            $site,
            $request->input('name'),
            $request->user()
        );

        return response()->json(['data' => $newSite], 201);
    }

    public function export(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $exported = $this->cloneService->export($site);

        return response()->json(['data' => $exported]);
    }

    public function importTemplate(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'template_json' => ['required', 'string'],
        ]);

        $result = $this->cloneService->importFromTemplate(
            $request->input('template_json'),
            $site
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
