<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tags\Services\TagService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(private TagService $tagService) {}

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $tags = $site->tags()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tags]);
    }

    public function store(CreateTagRequest $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $tag = $this->tagService->createTag($request->validated(), $site);

        return response()->json(['data' => $tag], 201);
    }

    public function show(Site $site, Tag $tag): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json(['data' => $tag->loadCount('posts')]);
    }

    public function update(UpdateTagRequest $request, Site $site, Tag $tag): JsonResponse
    {
        $this->authorize('update', $site);

        $tag = $this->tagService->updateTag($tag, $request->validated());

        return response()->json(['data' => $tag]);
    }

    public function destroy(Site $site, Tag $tag): JsonResponse
    {
        $this->authorize('update', $site);

        $this->tagService->deleteTag($tag);

        return response()->json(null, 204);
    }

    public function merge(Request $request, Site $site, Tag $tag): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'target_tag_id' => ['required', 'uuid', 'exists:tags,id'],
        ]);

        $target = Tag::findOrFail($request->input('target_tag_id'));
        $result = $this->tagService->mergeTags($tag, $target);

        return response()->json(['data' => $result]);
    }
}
