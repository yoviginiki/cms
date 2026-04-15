<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Categories\Services\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService)
    {
    }

    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        return response()->json([
            'data' => $this->categoryService->getCategoryTree($site),
        ]);
    }

    public function show(Site $site, Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $category->load('children')->loadCount('posts');

        return (new CategoryResource($category))->response();
    }

    public function store(CreateCategoryRequest $request, Site $site): JsonResponse
    {
        $this->authorize('create', [Category::class, $site]);

        $category = $this->categoryService->createCategory($request->validated(), $site);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Site $site, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->updateCategory($category, $request->validated());

        return (new CategoryResource($category))->response();
    }

    public function destroy(Site $site, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        // Detach posts, don't delete them
        $category->posts()->update(['category_id' => null]);
        $category->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request, Site $site): JsonResponse
    {
        $this->authorize('create', [Category::class, $site]);

        $this->categoryService->reorderCategories($site, $request->validated('items'));

        return response()->json(['message' => 'Categories reordered.']);
    }
}
