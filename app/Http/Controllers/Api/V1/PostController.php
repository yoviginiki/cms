<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Posts\Services\PostService;
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
    public function __construct(private PostService $postService)
    {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = $site->posts()
            ->with('category')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return PostResource::collection($query->paginate($request->integer('per_page', 15)))->response();
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

        $post = $this->postService->updatePost($post, $request->validated());

        return (new PostResource($post->load('category')))->response();
    }

    public function destroy(Site $site, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(null, 204);
    }
}
