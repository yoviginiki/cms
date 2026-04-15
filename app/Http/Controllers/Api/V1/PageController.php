<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pages\Services\PageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePageRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(private PageService $pageService)
    {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        if ($request->boolean('tree')) {
            return response()->json([
                'data' => $this->pageService->getPageTree($site),
            ]);
        }

        $query = $site->pages()->orderBy('sort_order');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return PageResource::collection($query->paginate($request->integer('per_page', 15)))->response();
    }

    public function show(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $page->load(['blocks' => fn($q) => $q->whereNull('parent_block_id')->orderBy('order')->with('children')]);

        return (new PageResource($page))->response();
    }

    public function store(CreatePageRequest $request, Site $site): JsonResponse
    {
        $this->authorize('create', [Page::class, $site]);

        $page = $this->pageService->createPage($request->validated(), $site);

        return (new PageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePageRequest $request, Site $site, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $page = $this->pageService->updatePage($page, $request->validated());

        return (new PageResource($page))->response();
    }

    public function destroy(Site $site, Page $page): JsonResponse
    {
        $this->authorize('delete', $page);

        $page->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request, Site $site): JsonResponse
    {
        $this->authorize('reorder', [Page::class, $site]);

        $this->pageService->reorderPages($site, $request->validated('items'));

        return response()->json(['message' => 'Pages reordered.']);
    }
}
