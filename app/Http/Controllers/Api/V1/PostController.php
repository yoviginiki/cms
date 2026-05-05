<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Posts\Services\PostService;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private PostService $postService,
        private AutoPublishService $autoPublish,
    ) {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = $site->posts()->with(['category', 'grid:id,name,slug']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "{$search}%");
            });
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $sortField = $request->query('sort', 'published_at');
        $sortDir = $request->query('dir', 'desc');
        if (in_array($sortField, ['published_at', 'created_at', 'updated_at', 'title'])) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }
        if ($sortField !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        return PostResource::collection($query->paginate($request->integer('per_page', 50)))->response();
    }

    public function show(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->load([
            'category',
            'blocks' => fn($q) => $q->whereNull('parent_block_id')->orderBy('order')->with('children'),
        ]);

        return (new PostResource($post))->response();
    }

    public function store(CreatePostRequest $request, Site $site): JsonResponse
    {
        $this->authorize('create', [Post::class, $site]);

        $post = $this->postService->createPost($request->validated(), $site);

        return (new PostResource($post->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Site $site, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $oldStatus = $post->status;
        $post = $this->postService->updatePost($post, $request->validated());

        if ($post->status === 'published' || $oldStatus === 'published') {
            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'post_updated', $post->id);
        }

        return (new PostResource($post->load('category')))->response();
    }

    public function destroy(Site $site, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(null, 204);
    }
}
