<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ai\Services\ContentAssistant;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private ContentAssistant $ai) {}

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'context' => ['sometimes', 'array'],
        ]);

        if (!$this->ai->isEnabled()) {
            return response()->json(['message' => 'AI features are not enabled.'], 503);
        }

        $result = $this->ai->generateText(
            $request->input('prompt'),
            $request->input('context', [])
        );

        return response()->json(['data' => ['content' => $result]]);
    }

    public function rewrite(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string'],
            'instruction' => ['required', 'string', 'max:500'],
            'context' => ['sometimes', 'array'],
        ]);

        if (!$this->ai->isEnabled()) {
            return response()->json(['message' => 'AI features are not enabled.'], 503);
        }

        $result = $this->ai->rewrite(
            $request->input('content'),
            $request->input('instruction'),
            $request->input('context', [])
        );

        return response()->json(['data' => ['content' => $result]]);
    }

    public function translate(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string'],
            'language' => ['required', 'string', 'max:50'],
        ]);

        if (!$this->ai->isEnabled()) {
            return response()->json(['message' => 'AI features are not enabled.'], 503);
        }

        $result = $this->ai->translate(
            $request->input('content'),
            $request->input('language')
        );

        return response()->json(['data' => ['content' => $result]]);
    }

    public function seoSuggest(Site $site, Page $page): JsonResponse
    {
        $this->authorize('update', $site);

        if (!$this->ai->isEnabled()) {
            return response()->json(['message' => 'AI features are not enabled.'], 503);
        }

        $blocks = $page->blocks()->orderBy('order')->get()->toArray();
        $result = $this->ai->generateSeoMeta($blocks, $site->name);

        return response()->json(['data' => $result]);
    }

    public function altText(Site $site, Asset $asset): JsonResponse
    {
        $this->authorize('update', $site);

        if (!$this->ai->isEnabled()) {
            return response()->json(['message' => 'AI features are not enabled.'], 503);
        }

        $url = url("/api/v1/sites/{$site->id}/assets/{$asset->id}/serve");
        $result = $this->ai->suggestAltText($url);

        return response()->json(['data' => ['alt_text' => $result]]);
    }
}
