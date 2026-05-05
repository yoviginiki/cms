<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\DiffService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class DiffController extends Controller
{
    public function __construct(private DiffService $diffService) {}

    public function diffPage(Site $site, Page $page): JsonResponse
    {
        $this->authorize('view', $site);
        return response()->json(['data' => $this->diffService->diff($page)]);
    }

    public function diffPost(Site $site, Post $post): JsonResponse
    {
        $this->authorize('view', $site);
        return response()->json(['data' => $this->diffService->diff($post)]);
    }
}
