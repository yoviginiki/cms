<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Magazine\Services\MagazineService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MagEditorController extends Controller
{
    public function __construct(
        private MagazineService $service,
    ) {}

    /**
     * GET /sites/{site}/pages/{page}/magazine — get document
     */
    public function show(Site $site, Page $page): JsonResponse
    {
        abort_if($page->site_id !== $site->id, 404);
        $this->authorize('view', $page);
        $doc = $this->service->getDocument($page);

        return response()->json(['data' => $doc]);
    }

    /**
     * PUT /sites/{site}/pages/{page}/magazine — sync document
     */
    public function sync(Request $request, Site $site, Page $page): JsonResponse
    {
        abort_if($page->site_id !== $site->id, 404);
        $this->authorize('update', $page);
        $request->validate([
            'pages' => 'required|array',
            'pages.*.page_number' => 'required|integer',
            'pages.*.page_size' => 'sometimes|array',
            'pages.*.margins' => 'sometimes|array',
            'elements' => 'required|array',
            'elements.*.type' => 'required|string',
            'elements.*.x' => 'required|numeric',
            'elements.*.y' => 'required|numeric',
            'elements.*.width' => 'required|numeric',
            'elements.*.height' => 'required|numeric',
            'elements.*.page_number' => 'required|integer',
            'elements.*.data' => 'sometimes|array',
            'elements.*.style' => 'sometimes|array',
            'elements.*.typography' => 'sometimes|nullable|array',
            'elements.*.text_wrap' => 'sometimes|array',
        ]);

        $this->service->syncDocument($page, $request->input('pages'), $request->input('elements'));

        return response()->json(['data' => $this->service->getDocument($page)]);
    }

    /**
     * POST /sites/{site}/pages/{page}/magazine/pages — add page
     */
    public function addPage(Request $request, Site $site, Page $page): JsonResponse
    {
        abort_if($page->site_id !== $site->id, 404);
        $this->authorize('update', $page);
        $request->validate([
            'after_page' => 'required|integer|min:0',
        ]);

        $magPage = $this->service->addPage($page, $request->input('after_page'));

        return response()->json(['data' => $magPage], 201);
    }

    /**
     * DELETE /sites/{site}/pages/{page}/magazine/pages/{pageNumber} — delete page
     */
    public function deletePage(Site $site, Page $page, int $pageNumber): JsonResponse
    {
        abort_if($page->site_id !== $site->id, 404);
        $this->authorize('update', $page);
        $this->service->deletePage($page, $pageNumber);

        return response()->json(['message' => 'Page deleted']);
    }
}
