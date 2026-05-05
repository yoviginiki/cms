<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\EditorPresenceService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorPresenceController extends Controller
{
    public function __construct(private EditorPresenceService $presence) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'page_id' => ['sometimes', 'uuid'],
            'post_id' => ['sometimes', 'uuid'],
        ]);

        $content = null;
        if ($request->has('page_id')) {
            $content = Page::findOrFail($request->input('page_id'));
        } elseif ($request->has('post_id')) {
            $content = Post::findOrFail($request->input('post_id'));
        }

        if (!$content) {
            return response()->json(['message' => 'page_id or post_id required'], 422);
        }

        $this->presence->heartbeat($request->user(), $content);

        return response()->json(['status' => 'ok']);
    }

    public function presence(Request $request, string $contentType, string $contentId): JsonResponse
    {
        $content = $contentType === 'pages'
            ? Page::findOrFail($contentId)
            : Post::findOrFail($contentId);

        $editors = $this->presence->getActiveEditors($content, $request->user()->id);

        return response()->json(['data' => $editors]);
    }
}
