<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\SanitizationService;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Domain\Publishing\Rendering\RenderContext;
use App\Domain\Publishing\Rendering\RenderMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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

        $html = $this->renderPreview($page, $site, $this->editModeFor($page));

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

        $html = $this->renderPreview($post, $site, $this->editModeFor($post));

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
     * Generate a temporary shareable preview token (F21). The token is bound
     * to tenant + site + content type + content id, and the content must
     * belong to this site; the URL is the named public route.
     */
    public function createPreviewToken(Request $request, Site $site, string $contentType, string $contentId): JsonResponse
    {
        $this->authorize('update', $site);

        $type = match ($contentType) {
            'page', 'pages' => 'page',
            'post', 'posts' => 'post',
            default => null,
        };
        if ($type === null || !Str::isUuid($contentId)) {
            return response()->json(['message' => 'Unknown content type or id.'], 422);
        }
        $content = $type === 'page'
            ? Page::where('site_id', $site->id)->find($contentId)
            : Post::where('site_id', $site->id)->find($contentId);
        if (!$content) {
            return response()->json(['message' => 'Content not found on this site.'], 404);
        }

        $token = Str::random(64);
        $expires = now()->addHours(24);
        Cache::put("preview_token:" . hash('sha256', $token), [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'content_type' => $type,
            'content_id' => $content->id,
        ], $expires);

        return response()->json([
            'data' => [
                'token' => $token,
                'url' => route('preview.public', ['token' => $token]),
                'expires_at' => $expires->toISOString(),
            ],
        ]);
    }

    /**
     * Public preview via token (no auth required). Read-only render in the
     * token's tenant context — no editor message listener, no edit mode.
     */
    public function publicPreview(string $token): Response
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            abort(404);
        }
        $data = Cache::get("preview_token:" . hash('sha256', $token));
        if (!$data || empty($data['tenant_id'])) {
            abort(404);
        }

        return \App\Domain\Tenancy\PublicTenantResolver::withTenant((string) $data['tenant_id'], function () use ($data) {
            $site = Site::find($data['site_id']);
            $content = $data['content_type'] === 'page'
                ? ($site ? Page::where('site_id', $site->id)->find($data['content_id']) : null)
                : ($site ? Post::where('site_id', $site->id)->find($data['content_id']) : null);
            if (!$site || !$content) {
                abort(404);
            }

            $html = $this->renderPreview($content, $site, false, liveUpdates: false);

            return response($html, 200)
                ->header('Content-Type', 'text/html')
                ->header('X-Robots-Tag', 'noindex')
                ->header('Cache-Control', 'no-store');
        });
    }

    /**
     * Render preview HTML with postMessage listener for live updates.
     */
    /**
     * Decide whether inline edit mode is allowed for this request.
     *
     * Gated on the explicit ?sp_edit=1 flag AND the caller's ability to update
     * the content. The update ability is a provisional gate; Phase 4 replaces it
     * with the dedicated page.inline_edit ability.
     */
    private function editModeFor(Page|Post $content): bool
    {
        return request()->query('sp_edit') === '1' && Gate::allows('inlineEdit', $content);
    }

    /**
     * @param  bool  $liveUpdates  authenticated editor previews get the
     *         postMessage listener (parent = admin origin only); the shared
     *         public preview is read-only and gets none (F21).
     */
    private function renderPreview(Page|Post $content, Site $site, bool $editMode = false, bool $liveUpdates = true): string
    {
        $site->load('theme');

        // Edit mode renders the SAME page through RenderMode::Edit so block
        // partials emit data-sp-* addressing. The publish/view path never sets
        // this, so its bytes are unchanged.
        $html = $editMode
            ? app(RenderContext::class)->runIn(
                RenderMode::Edit,
                fn () => $this->buildService->build($content, $site->theme, $site),
            )
            : $this->buildService->build($content, $site->theme, $site);

        if ($liveUpdates) {
            // Live-update listener: only messages from the admin origin AND from
            // the embedding window are honoured; both message kinds are
            // reachable (the old early return made reload dead code); block
            // targets use the renderer's own ids (data-sp-block / data-block-id).
            $origin = json_encode(rtrim((string) config('app.url'), '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $previewScript = '<script>(function(){var ORIGIN=' . $origin . ';'
                . 'window.addEventListener("message",function(event){'
                . 'if(event.origin!==ORIGIN)return;'
                . 'if(window.parent===window||event.source!==window.parent)return;'
                . 'var d=event.data;if(!d||typeof d!=="object"||typeof d.type!=="string")return;'
                . 'if(d.type==="cms-preview-reload"){window.location.reload();return;}'
                . 'if(d.type!=="cms-preview-update")return;'
                . 'if(typeof d.blockId!=="string"||typeof d.html!=="string"||!/^[A-Za-z0-9-]{1,64}$/.test(d.blockId))return;'
                . 'var el=document.querySelector(\'[data-sp-block="\'+d.blockId+\'"],[data-block-id="\'+d.blockId+\'"]\');'
                . 'if(el){el.innerHTML=d.html;}'
                . '});})();</script>';

            // Add data-block-id attributes to rendered blocks
            $html = $this->addBlockIds($html, $content);
            $html = str_replace('</body>', $previewScript . '</body>', $html);
        }

        // Inline-edit overlay — lazily injected only in edit mode, from the
        // admin origin. Nothing here on the plain preview/view path.
        if ($editMode) {
            $html = str_replace('</body>', $this->overlayBootstrap($content) . '</body>', $html);
        }

        // Experience Mode runtime — inject when cinematic or ?experience=1
        $isExperience = ($content->experience_mode ?? 'standard') === 'cinematic'
            || request()->query('experience') === '1';

        if ($isExperience) {
            $experienceAssets = '<link rel="stylesheet" href="/assets/experience/experience-runtime.a44ae8ee.css">'
                . "\n" . '<script defer src="/assets/experience/experience-runtime.a44ae8ee.js"></script>';
            $html = str_replace('</head>', $experienceAssets . "\n</head>", $html);
        }

        return $html;
    }

    /**
     * Bootstrap config + lazy loader for the inline-edit overlay bundle.
     */
    private function overlayBootstrap(Page|Post $content): string
    {
        $config = json_encode([
            'pageId' => $content->id,
            'versionId' => null,
            'parentOrigin' => rtrim(config('app.url'), '/'),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<script>window.__SP_EDIT=' . $config . ';</script>'
            . '<script src="/inline-edit/overlay.js" defer></script>';
    }

    /**
     * Add data-block-id attributes to top-level block wrappers for live
     * preview targeting. Deterministic (F21): blocks are walked in render
     * order with a moving cursor, so two blocks of the same type each get
     * THEIR id (the old class-name heuristic always hit the first match).
     * Wrappers already addressed by the renderer (data-sp-block) are kept.
     */
    private function addBlockIds(string $html, Page|Post $content): string
    {
        $blocks = $content->blocks()->whereNull('parent_block_id')->orderBy('order')->get();
        $cursor = 0;
        foreach ($blocks as $block) {
            if (str_contains($html, 'data-sp-block="' . $block->id . '"') || str_contains($html, 'data-block-id="' . $block->id . '"')) {
                continue;
            }
            $needle = 'class="' . $block->type . '-block';
            $pos = strpos($html, $needle, $cursor);
            if ($pos === false) {
                continue; // block type renders without the conventional wrapper
            }
            $insert = 'data-block-id="' . $block->id . '" ';
            $html = substr_replace($html, $insert . $needle, $pos, strlen($needle));
            $cursor = $pos + strlen($insert) + strlen($needle);
        }

        return $html;
    }
}
