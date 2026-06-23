<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\SanitizationService;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PreviewController extends Controller
{
    public function __construct(
        private BuildPageService $buildService,
        private SanitizationService $sanitizer,
    ) {
    }

    /**
     * Render full page preview (auth required).
     */
    public function previewPage(Site $site, Page $page): Response
    {
        $this->authorize('view', $site);

        $html = $this->renderPreview($page, $site);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Render full post preview (auth required).
     */
    public function previewPost(Site $site, Post $post): Response
    {
        $this->authorize('view', $site);

        $html = $this->renderPreview($post, $site);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Render a single block (for live preview updates).
     */
    public function renderBlock(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $request->validate([
            'type' => ['required', 'string'],
            'data' => ['required', 'array'],
        ]);

        $type = $request->input('type');
        $data = $request->input('data');

        $viewName = "blocks.{$type}";
        if (!View::exists($viewName)) {
            return response()->json(['html' => "<!-- Unknown block type: {$type} -->"], 200);
        }

        // Create a temporary block for sanitization
        $block = new Block(['type' => $type, 'data' => $data]);
        $sanitizedData = $this->sanitizer->sanitizeBlock($block);

        $html = View::make($viewName, [
            'data' => $sanitizedData,
            'children' => '',
            'site' => $site,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Generate a temporary shareable preview token.
     */
    public function createPreviewToken(Request $request, Site $site, string $contentType, string $contentId): JsonResponse
    {
        $this->authorize('update', $site);

        $token = Str::random(64);
        $key = "preview_token:{$token}";

        Cache::put($key, [
            'site_id' => $site->id,
            'content_type' => $contentType,
            'content_id' => $contentId,
        ], now()->addHours(24));

        return response()->json([
            'data' => [
                'token' => $token,
                'url' => url("/preview/{$token}"),
                'expires_at' => now()->addHours(24)->toISOString(),
            ],
        ]);
    }

    /**
     * Public preview via token (no auth required).
     */
    public function publicPreview(string $token): Response
    {
        $key = "preview_token:{$token}";
        $data = Cache::get($key);

        if (!$data) {
            abort(404);
        }

        $site = Site::findOrFail($data['site_id']);

        $content = $data['content_type'] === 'page'
            ? Page::findOrFail($data['content_id'])
            : Post::findOrFail($data['content_id']);

        $html = $this->renderPreview($content, $site);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Render preview HTML with postMessage listener for live updates.
     */
    private function renderPreview(Page|Post $content, Site $site): string
    {
        $site->load('theme');
        $html = $this->buildService->build($content, $site->theme, $site);

        // Inject preview script for live updates via postMessage
        $previewScript = <<<'JS'
<script>
(function() {
    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'cms-preview-update') return;
        var blockId = event.data.blockId;
        var html = event.data.html;
        if (blockId && html !== undefined) {
            var el = document.querySelector('[data-block-id="' + blockId + '"]');
            if (el) {
                el.innerHTML = html;
            }
        }
        if (event.data.type === 'cms-preview-reload') {
            window.location.reload();
        }
    });
})();
</script>
JS;

        // Add data-block-id attributes to rendered blocks
        $html = $this->addBlockIds($html, $content);

        // Insert preview script before </body>
        $html = str_replace('</body>', $previewScript . '</body>', $html);

        // Experience Mode runtime — inject when cinematic or ?experience=1
        $isExperience = ($content->experience_mode ?? 'standard') === 'cinematic'
            || request()->query('experience') === '1';

        if ($isExperience) {
            $experienceAssets = '<link rel="stylesheet" href="/assets/experience/experience-runtime.8898c878.css">'
                . "\n" . '<script defer src="/assets/experience/experience-runtime.8898c878.js"></script>';
            $html = str_replace('</head>', $experienceAssets . "\n</head>", $html);
        }

        return $html;
    }

    /**
     * Add data-block-id attributes to block wrappers for live preview targeting.
     */
    private function addBlockIds(string $html, Page|Post $content): string
    {
        $blocks = $content->blocks()->whereNull('parent_block_id')->orderBy('order')->get();
        $blockTypes = [
            'text-block' => 'div',
            'image-block' => 'figure',
            'hero-section' => 'section',
            'columns-block' => 'div',
            'quote-block' => 'blockquote',
            'divider-block' => 'hr',
        ];

        // Simple approach: wrap each top-level rendered block section with data attribute
        // This works because blocks render in order
        foreach ($blocks as $block) {
            $viewName = "blocks.{$block->type}";
            // Add data-block-id to the first tag of each rendered block
            $patterns = [
                "class=\"text-block" => "data-block-id=\"{$block->id}\" class=\"text-block",
                "class=\"image-block" => "data-block-id=\"{$block->id}\" class=\"image-block",
                "class=\"hero-section" => "data-block-id=\"{$block->id}\" class=\"hero-section",
                "class=\"columns-block" => "data-block-id=\"{$block->id}\" class=\"columns-block",
                "class=\"quote-block" => "data-block-id=\"{$block->id}\" class=\"quote-block",
                "class=\"divider-block" => "data-block-id=\"{$block->id}\" class=\"divider-block",
            ];

            foreach ($patterns as $search => $replace) {
                // Only replace the first occurrence for this block
                $pos = strpos($html, $search);
                if ($pos !== false && !str_contains(substr($html, max(0, $pos - 100), 100), "data-block-id=\"{$block->id}\"")) {
                    $html = substr_replace($html, $replace, $pos, strlen($search));
                    break;
                }
            }
        }

        return $html;
    }
}
