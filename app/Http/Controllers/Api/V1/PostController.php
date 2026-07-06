<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Posts\Services\PostService;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\References\Services\StalenessResolver;
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
        private StalenessResolver $staleness,
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

        if ($post->status === 'published') {
            // Listing pages (category + unfiltered "latest posts") now show stale lists
            $this->staleness->resolveForPostChange($site, $post, "New post '{$post->title}' published");
        }

        return (new PostResource($post->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Site $site, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $oldStatus = $post->status;
        $oldSlug = $post->slug;
        $post = $this->postService->updatePost($post, $request->validated());

        if ($post->status === 'published' || $oldStatus === 'published') {
            // Listing pages + postcards embedding this post are now stale
            $this->staleness->resolveForPostChange($site, $post, "Post '{$post->title}' updated");

            // Slug change: referrers still contain the old URL
            if ($post->slug !== $oldSlug && $oldStatus === 'published') {
                $this->staleness->markStaleForLinkTargets(
                    $site, 'post', $post->id,
                    "Internal link target renamed: /blog/{$oldSlug} → /blog/{$post->slug}",
                );
            }

            $this->autoPublish->triggerIfEnabled($site, $request->user(), 'post_updated', $post->id);
        }

        return (new PostResource($post->load('category')))->response();
    }

    public function destroy(Site $site, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $wasPublished = $post->status === 'published';
        $post->delete();

        if ($wasPublished) {
            // Listings still show it; referrers now contain a dead link
            $this->staleness->resolveForPostChange($site, $post, "Post '{$post->title}' deleted");
            $this->staleness->markStaleForLinkTargets(
                $site, 'post', $post->id,
                "Linked post '{$post->title}' deleted",
            );
        }

        return response()->json(null, 204);
    }


    public function translate(Site $site, Post $post, \Illuminate\Http\Request $request): JsonResponse
    {
        $this->authorize('create', [Post::class, $site]);
        $locale = (string) $request->validate(['locale' => ['required', 'string', 'max:10']])['locale'];

        $translation = app(\App\Domain\Publishing\Services\TranslationService::class)
            ->translate($post, $locale, $site);

        return (new PostResource($translation))->response()->setStatusCode(201);
    }

    public function translations(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $rows = [];
        foreach (app(\App\Domain\Publishing\Services\TranslationService::class)->siblings($post, $site) as $locale => $sibling) {
            $rows[] = [
                'locale' => $locale,
                'id' => $sibling->id,
                'title' => $sibling->title,
                'slug' => $sibling->slug,
                'status' => $sibling->status,
            ];
        }

        return response()->json(['data' => $rows]);
    }

    public function duplicate(Site $site, Post $post): JsonResponse
    {
        $this->authorize('create', [Post::class, $site]);

        $newPost = $post->replicate(['id', 'slug', 'created_at', 'updated_at', 'published_at']);
        $newPost->title = $post->title . ' (Copy)';
        $newPost->slug = $post->slug . '-copy-' . substr(md5(now()->timestamp), 0, 4);
        $newPost->status = 'draft';
        $newPost->save();

        // Copy all blocks with remapped IDs
        $blocks = \App\Models\Block::where('blockable_type', $post->getMorphClass())
            ->where('blockable_id', $post->getKey())
            ->orderBy('order')
            ->get();

        $idMap = [];
        foreach ($blocks as $block) {
            $idMap[$block->id] = \Illuminate\Support\Str::uuid()->toString();
        }

        foreach ($blocks as $block) {
            \App\Models\Block::create([
                'id' => $idMap[$block->id],
                'blockable_type' => $newPost->getMorphClass(),
                'blockable_id' => $newPost->getKey(),
                'parent_block_id' => $block->parent_block_id ? ($idMap[$block->parent_block_id] ?? null) : null,
                'type' => $block->type,
                'data' => $block->data,
                'order' => $block->order,
                'style' => $block->style,
            ]);
        }

        return (new PostResource($newPost->load('category')))->response()->setStatusCode(201);
    }
}
