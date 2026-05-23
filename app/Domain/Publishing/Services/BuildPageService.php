<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Grid\Services\GridResolver;
use App\Domain\Hooks\HookDispatcher;
use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Models\Asset;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\View;

class BuildPageService
{
    private int $imageIndex = 0;
    private bool $isPreview = false;
    private array $templateContext = [];

    public function __construct(
        private SanitizationService $sanitizer,
        private SeoService $seoService,
        private HtmlMinifier $minifier,
        private OutputValidator $validator,
        private MenuRenderer $menuRenderer,
        private GridResolver $gridResolver,
        private GridRenderer $gridRenderer,
        private DesignTokenGenerator $tokenGenerator,
        private HookDispatcher $hooks,
        private MagazineRenderer $magazineRenderer,
    ) {}

    public function build(Page|Post $content, ?Theme $theme, Site $site, bool $isPreview = false): string
    {
        $this->imageIndex = 0;
        $this->isPreview = $isPreview;
        $this->templateContext = [];

        $headContent = $this->seoService->generatePageHead($content, $site);
        $themeConfig = $theme?->config ?? [];

        // Script injection
        $settings = $site->settings ?? [];
        $headScripts = ($settings['head_scripts'] ?? '') . ($content->seo_meta['head_scripts'] ?? '');
        $bodyScripts = ($settings['body_scripts'] ?? '') . ($content->seo_meta['body_scripts'] ?? '');
        $customCss = ($settings['custom_css'] ?? '') . ($content->seo_meta['custom_css'] ?? '');

        // Page appearance CSS from seo_meta
        $pageMeta = $content->seo_meta ?? [];
        $pageAppearanceCss = $this->buildPageAppearanceCss($pageMeta);
        if ($pageAppearanceCss) $customCss .= "\n" . $pageAppearanceCss;

        $criticalCss = $this->buildCriticalCss($themeConfig);
        $fontPreloads = $this->buildFontPreloads($themeConfig);
        $rssUrl = ($site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu") . '/feed.xml';

        // Design tokens CSS
        $designTokensCss = $this->tokenGenerator->generate($site);

        // Hook outputs
        $hookHeadScripts = $this->hooks->collectAction('head_scripts', $content, $site);
        $hookBodyOpen = $this->hooks->collectAction('body_open', $content, $site);
        $hookBodyClose = $this->hooks->collectAction('body_close', $content, $site);

        // Magazine editor mode
        if ($content instanceof Page && $content->editor_mode === 'magazine') {
            $magazineHtml = $this->magazineRenderer->render($content, $site);

            // Resolve layout for magazine pages too
            $layout = $this->resolveLayout($content);
            $layoutSlug = $layout?->slug ?? 'standard';

            if ($layout && $layoutSlug !== 'standard' && $content->layout_id && $layout->wrapper_blade_view && View::exists($layout->wrapper_blade_view)) {
                $bodyContent = View::make($layout->wrapper_blade_view, [
                    'blocksHtml' => $magazineHtml,
                    'layout' => $layout,
                    'site' => $site,
                    'content' => $content,
                ])->render();
            } else {
                $headerNav = $this->menuRenderer->renderByLocation($site, 'header');
                $footerNav = $this->menuRenderer->renderByLocation($site, 'footer');
                $bodyContent = ($headerNav ?: ($themeConfig['navigation_html'] ?? ''))
                    . $magazineHtml
                    . ($footerNav ?? '');
            }

            $html = View::make('publishing.layout', [
                'headContent' => $headContent,
                'headScripts' => $headScripts,
                'bodyScripts' => $bodyScripts,
                'customCss' => $customCss,
                'criticalCss' => $criticalCss,
                'fontPreloads' => $fontPreloads,
                'cssFile' => $themeConfig['css_file'] ?? null,
                'navigation' => '',
                'footerNavigation' => '',
                'renderedBlocks' => $bodyContent,
                'designTokensCss' => $designTokensCss ?? '',
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'content' => $content,
                'themeConfig' => $themeConfig,
                'lang' => $themeConfig['lang'] ?? 'en',
            ])->render();

            $html = $this->hooks->applyFilter('page_render', $html, $content, $site);
            if (!$this->isPreview) {
                $html = AssetPublisher::rewriteHtml($html);
            }

            return $this->minifier->minify($html);
        }

        // Check if content has an explicit non-standard layout — if so, skip grid
        $explicitLayout = $content->layout_id ? \App\Models\Layout::find($content->layout_id) : null;
        $useLayout = $explicitLayout && $explicitLayout->slug !== 'standard';

        // Try grid-based rendering (only if no explicit layout override)
        $grid = !$useLayout ? $this->gridResolver->resolve($content, $site) : null;

        if ($grid) {
            $gridResult = $this->gridRenderer->render($grid, $content, $site);

            $html = View::make('publishing.grid-layout', [
                'headContent' => $headContent,
                'headScripts' => $headScripts,
                'bodyScripts' => $bodyScripts,
                'customCss' => $customCss,
                'criticalCss' => $criticalCss,
                'fontPreloads' => $fontPreloads,
                'cssFile' => $themeConfig['css_file'] ?? null,
                'gridCss' => $gridResult['css'],
                'gridHtml' => $gridResult['html'],
                'designTokensCss' => $designTokensCss,
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'lang' => $themeConfig['lang'] ?? 'en',
            ])->render();
        } else {
            // Check for theme template (posts only)
            $template = ($content instanceof Post)
                ? ThemeTemplate::resolveForPost($content)
                : null;

            if ($template) {
                $renderedBlocks = $this->renderTemplatedPost($content, $template, $site);
            } else {
                // Standard: render content's own blocks
                $blocks = $content->blocks()
                    ->whereNull('parent_block_id')
                    ->orderBy('order')
                    ->with('children')
                    ->get();

                $renderedBlocks = '';
                foreach ($blocks as $block) {
                    $renderedBlocks .= $this->renderBlock($block, $site);
                }

                // Append raw HTML content (preserved verbatim — scripts, embeds, custom HTML)
                if ($content instanceof Page && $content->raw_html) {
                    $renderedBlocks .= $content->raw_html;
                }
            }

            // Use the already-resolved layout from the grid check above
            $layout = $explicitLayout;
            $layoutSlug = $layout?->slug ?? 'standard';

            // Use layout wrapper if not standard
            if ($layout && $layoutSlug !== 'standard' && $layout->wrapper_blade_view && View::exists($layout->wrapper_blade_view)) {
                $bodyContent = View::make($layout->wrapper_blade_view, [
                    'blocksHtml' => $renderedBlocks,
                    'layout' => $layout,
                    'site' => $site,
                    'content' => $content,
                ])->render();
            } else {
                // Standard: check for template-based header/footer, fall back to menu
                $headerHtml = $this->renderGlobalTemplate($site, 'header');
                $footerHtml = $this->renderGlobalTemplate($site, 'footer');

                if (!$headerHtml) {
                    $headerHtml = $this->menuRenderer->renderByLocation($site, 'header') ?: ($themeConfig['navigation_html'] ?? '');
                }
                if (!$footerHtml) {
                    $footerHtml = $this->menuRenderer->renderByLocation($site, 'footer') ?: '';
                }

                $bodyContent = $renderedBlocks;
            }

            $html = View::make('publishing.layout', [
                'headContent' => $headContent,
                'headScripts' => $headScripts,
                'bodyScripts' => $bodyScripts,
                'customCss' => $customCss,
                'criticalCss' => $criticalCss,
                'fontPreloads' => $fontPreloads,
                'cssFile' => $themeConfig['css_file'] ?? null,
                'navigation' => ($layoutSlug === 'standard') ? ($headerHtml ?? '') : '',
                'footerNavigation' => ($layoutSlug === 'standard') ? ($footerHtml ?? '') : '',
                'renderedBlocks' => $bodyContent,
                'mainStyle' => ($layoutSlug === 'standard') ? 'max-width:var(--container-width, 1200px);margin:0 auto;padding:0 var(--container-padding, 24px);' : '',
                'designTokensCss' => $designTokensCss ?? '',
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'content' => $content,
                'themeConfig' => $themeConfig,
                'lang' => $themeConfig['lang'] ?? 'en',
            ])->render();
        }

        // Apply page_render filter hook
        $html = $this->hooks->applyFilter('page_render', $html, $content, $site);

        // Rewrite all /api/v1/.../assets/.../serve URLs to static public paths
        // This ensures images and files work on the static site without the backend running
        // Skip during preview — API serve URLs work with auth cookie on the admin domain
        if (!$this->isPreview) {
            $html = AssetPublisher::rewriteHtml($html);
        }

        return $this->minifier->minify($html);
    }

    /**
     * Validate built HTML against Lighthouse constraints.
     */
    public function buildAndValidate(Page|Post $content, ?Theme $theme, Site $site): array
    {
        $html = $this->build($content, $theme, $site);
        $validation = $this->validator->validate($html, $content, $site);

        return [
            'html' => $html,
            'validation' => $validation,
        ];
    }

    public function renderBlock(Block $block, Site $site): string
    {
        $sanitizedData = $this->sanitizer->sanitizeBlock($block);
        $childrenHtml = '';
        $childrenArray = [];

        foreach ($block->children()->orderBy('order')->get() as $child) {
            $rendered = $this->renderBlock($child, $site);
            $childrenHtml .= $rendered;
            $childrenArray[] = $rendered;
        }

        // Enrich image blocks with asset data
        if ($block->type === 'image') {
            $sanitizedData = $this->enrichImageData($sanitizedData, $site);
        }

        // Enrich flipbook blocks — resolve PDF asset to a public URL
        if ($block->type === 'flipbook' && !empty($sanitizedData['pdf_asset_id'])) {
            $sanitizedData = $this->enrichFlipbookData($sanitizedData, $site);
        }

        $viewName = "blocks.{$block->type}";
        if (!View::exists($viewName)) {
            return "<!-- Unknown block type: {$block->type} -->";
        }

        // Extract shared properties stored in data.__style, __animation, __advanced
        $blockStyle = $block->style ?? $sanitizedData['__style'] ?? [];
        $blockAnimation = $sanitizedData['__animation'] ?? [];
        $blockAdvanced = $sanitizedData['__advanced'] ?? [];
        $blockResponsive = $sanitizedData['__responsive'] ?? [];

        // Build responsive style overrides (spacing/layout per breakpoint)
        $htmlId = \App\Support\Blocks\BlockStyle::safeId($blockAdvanced['htmlId'] ?? '');
        $respStyleCss = \App\Support\Blocks\BlockStyle::buildResponsiveStyleCss(
            is_array($block->responsive) ? $block->responsive : $blockResponsive,
            $htmlId ?: $block->id,
        );

        $rendered = View::make($viewName, array_merge([
            'data' => $sanitizedData,
            'children' => $childrenHtml,
            'childrenArray' => $childrenArray,
            'site' => $site,
            'blockStyle' => $blockStyle,
            'blockAnimation' => $blockAnimation,
            'blockAdvanced' => $blockAdvanced,
            'blockResponsive' => $blockResponsive,
        ], $this->templateContext))->render();

        // Wrap with responsive style overrides if any exist
        if ($respStyleCss['css']) {
            $rendered = '<style>' . $respStyleCss['css'] . '</style>'
                . '<div class="' . $respStyleCss['scopeClass'] . '">' . $rendered . '</div>';
        }

        return $rendered;
    }

    /**
     * Render a post using a theme template.
     * Template blocks are rendered with post data injected as context.
     */
    private function renderTemplatedPost(Post $post, ThemeTemplate $template, Site $site): string
    {
        // First render the post's own blocks as the "post content" HTML
        $postBlocks = $post->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $postContentHtml = '';
        foreach ($postBlocks as $block) {
            $postContentHtml .= $this->renderBlock($block, $site);
        }

        // Resolve prev/next posts in same category
        $prevPost = null;
        $nextPost = null;
        if ($post->category_id) {
            $prevPost = Post::where('site_id', $post->site_id)
                ->where('category_id', $post->category_id)
                ->where('status', 'published')
                ->where('published_at', '<', $post->published_at)
                ->orderByDesc('published_at')
                ->first();
            $nextPost = Post::where('site_id', $post->site_id)
                ->where('category_id', $post->category_id)
                ->where('status', 'published')
                ->where('published_at', '>', $post->published_at)
                ->orderBy('published_at')
                ->first();
        }

        // Load post relationships for dynamic blocks
        $post->loadMissing(['category', 'author']);

        // Set template context — these variables are passed to all Blade views
        $this->templateContext = [
            '__post' => $post,
            '__postContentHtml' => $postContentHtml,
            '__prevPost' => $prevPost,
            '__nextPost' => $nextPost,
        ];

        // Render template blocks (try/finally ensures context is always cleared)
        try {
            $templateBlocks = $template->blocks()
                ->whereNull('parent_block_id')
                ->orderBy('order')
                ->with('children')
                ->get();

            $html = '';
            foreach ($templateBlocks as $block) {
                $html .= $this->renderBlock($block, $site);
            }

            return $html;
        } finally {
            $this->templateContext = [];
        }
    }

    /**
     * Render blocks with a specific template context (used by archive renderer).
     */
    public function renderBlocksWithContext(iterable $blocks, Site $site, array $context): string
    {
        $this->templateContext = $context;
        try {
            $html = '';
            foreach ($blocks as $block) {
                $html .= $this->renderBlock($block, $site);
            }
            return $html;
        } finally {
            $this->templateContext = [];
        }
    }

    /**
     * Render a global header or footer template if one exists.
     */
    private function renderGlobalTemplate(Site $site, string $type): ?string
    {
        $template = ThemeTemplate::resolveGlobal($site->id, $type);
        if (!$template) return null;

        $blocks = $template->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        if ($blocks->isEmpty()) return null;

        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $site);
        }

        return $html;
    }

    /**
     * Resolve the layout for a page or post.
     */
    private function resolveLayout(Page|Post $content): ?\App\Models\Layout
    {
        try {
            $resolver = app(\App\Services\Layout\LayoutResolver::class);
            $layout = $content instanceof Post
                ? $resolver->resolveForPost($content)
                : $resolver->resolveForPage($content);
            return $layout;
        } catch (\Throwable $e) {
            logger()->warning('Layout resolve failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve asset_id to full variant URLs and dimensions for image blocks.
     */
    private function enrichImageData(array $data, Site $site): array
    {
        $this->imageIndex++;
        $data['_is_first_image'] = $this->imageIndex === 1;

        if (!empty($data['asset_id'])) {
            $asset = Asset::find($data['asset_id']);
            if ($asset) {
                $baseUrl = "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve";
                $data['url'] = $data['url'] ?: $baseUrl;

                // Dimensions
                if ($asset->dimensions) {
                    $data['width'] = $data['width'] ?? ($asset->dimensions['width'] ?? null);
                    $data['height'] = $data['height'] ?? ($asset->dimensions['height'] ?? null);
                }

                // Variant URLs
                $variants = [];
                foreach ($asset->variants as $variantName => $path) {
                    $variants[$variantName] = "{$baseUrl}/{$variantName}";
                }
                $data['variants'] = $variants;
            }
        }

        return $data;
    }

    /**
     * Resolve flipbook PDF asset to a publicly accessible URL.
     * Copies the PDF to public_html so it's served statically.
     */
    private function enrichFlipbookData(array $data, Site $site): array
    {
        $asset = Asset::find($data['pdf_asset_id']);
        if (!$asset || !$asset->storage_path) {
            return $data;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('assets');
        if (!$disk->exists($asset->storage_path)) {
            return $data;
        }

        // Copy PDF to a public path: /assets/pdf/{checksum}.pdf
        // public_html is the web-facing document root
        $publicDir = base_path('../../public_html/assets/pdf');
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $publicFilename = ($asset->checksum ?: md5($asset->id)) . '.pdf';
        $publicPath = $publicDir . '/' . $publicFilename;

        if (!file_exists($publicPath)) {
            $contents = $disk->get($asset->storage_path);
            file_put_contents($publicPath, $contents);
        }

        $data['pdf_url'] = '/assets/pdf/' . $publicFilename;

        return $data;
    }

    private function buildCriticalCss(array $themeConfig): string
    {
        $css = $themeConfig['critical_css'] ?? '';
        if (empty($css)) {
            // Default minimal critical CSS for common blocks
            $css = '
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;line-height:1.6;color:#1a1a1a}
main{max-width:var(--container-width,1200px);margin:0 auto;padding:0 var(--container-padding,1rem)}
main[style*="max-width:none"]{max-width:none;padding:0}
img{max-width:100%;height:auto;display:block}
.hero-section{position:relative;min-height:400px;display:flex;align-items:center;justify-content:center;color:#fff;background-size:cover;background-position:center}
.hero-content{position:relative;z-index:1}
.text-block{margin-bottom:1.5rem}
.prose{max-width:65ch}
.prose p{margin:0 0 1em}
.prose h2,.prose h3,.prose h4{margin:1.5em 0 0.5em}
.prose ul,.prose ol{padding-left:1.5em}
.columns-block{margin-bottom:1.5rem}
.image-block{margin-bottom:1.5rem}
.image-block figcaption{font-size:0.875rem;color:#666;margin-top:0.5rem;text-align:center}
.quote-block{border-left:4px solid #3b82f6;padding:1rem 1.5rem;margin:1.5rem 0;font-style:italic}
.quote-block cite{display:block;font-style:normal;font-weight:600;margin-top:0.5rem}
.divider-block{border:none;border-top:1px solid #e5e7eb;margin:2rem 0}
';
        }

        // Block entrance animations (must match BlockStyle::ANIMATION_NAMES)
        $css .= '
@keyframes block-fade{from{opacity:0}to{opacity:1}}
@keyframes block-slide-up{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes block-slide-down{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
@keyframes block-slide-left{from{opacity:0;transform:translateX(-30px)}to{opacity:1;transform:translateX(0)}}
@keyframes block-slide-right{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
@keyframes block-zoom{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:scale(1)}}
@keyframes block-scale-in{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}
@media(prefers-reduced-motion:reduce){[style*="animation-name"],[data-animation]{animation:none!important}}
.block-hover-opacity{transition:opacity .3s ease}.block-hover-opacity:hover{opacity:.7}
.block-hover-lift{transition:transform .3s ease,box-shadow .3s ease}.block-hover-lift:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,.12)}
.block-hover-glow{transition:box-shadow .3s ease}.block-hover-glow:hover{box-shadow:0 0 20px rgba(59,130,246,.4)}
.block-hover-scale{transition:transform .3s ease}.block-hover-scale:hover{transform:scale(1.03)}
.block-hover-darken{transition:filter .3s ease}.block-hover-darken:hover{filter:brightness(.85)}
.block-hover-grayscale{transition:filter .3s ease}.block-hover-grayscale:hover{filter:grayscale(100%)}
.block-hover-sepia{transition:filter .3s ease}.block-hover-sepia:hover{filter:sepia(100%)}
.block-hover-blur{transition:filter .3s ease}.block-hover-blur:hover{filter:blur(2px)}
.block-hover-saturate{transition:filter .3s ease}.block-hover-saturate:hover{filter:saturate(1.8)}
';

        return trim($css);
    }

    private function buildFontPreloads(array $themeConfig): string
    {
        $fonts = $themeConfig['fonts'] ?? [];
        $html = '';

        foreach ($fonts as $font) {
            if (!empty($font['woff2_url'])) {
                $html .= '<link rel="preload" href="' . e($font['woff2_url']) . '" as="font" type="font/woff2" crossorigin>' . "\n";
            }
        }

        // Add font-display: swap @font-face rules
        if (!empty($fonts)) {
            $html .= '<style>';
            foreach ($fonts as $font) {
                if (!empty($font['woff2_url']) && !empty($font['family'])) {
                    $weight = $font['weight'] ?? '400';
                    $html .= "@font-face{font-family:'{$font['family']}';src:url('{$font['woff2_url']}') format('woff2');font-weight:{$weight};font-style:normal;font-display:swap}";
                }
            }
            $html .= '</style>' . "\n";
        }

        return $html;
    }

    /**
     * Build CSS for page-level appearance settings (stored in seo_meta).
     */
    private function buildPageAppearanceCss(array $meta): string
    {
        $css = '';
        $safe = fn(string $v) => preg_replace('/[{}<>;\\\\]/', '', $v);

        // Main element styles
        $mainProps = [];
        if (!empty($meta['page_padding']) && $meta['page_padding'] !== '0') {
            $mainProps[] = "padding:" . $safe($meta['page_padding']);
        }
        if (!empty($meta['page_margin']) && $meta['page_margin'] !== '0') {
            $mainProps[] = "margin:" . $safe($meta['page_margin']);
        }
        if (!empty($meta['page_max_width']) && $meta['page_max_width'] !== 'none') {
            $mainProps[] = "max-width:" . $safe($meta['page_max_width']);
        }
        if (!empty($meta['page_min_height']) && $meta['page_min_height'] !== 'auto') {
            $mainProps[] = "min-height:" . $safe($meta['page_min_height']);
        }
        if (!empty($meta['page_shadow']) && $meta['page_shadow'] !== 'none') {
            $mainProps[] = "box-shadow:" . $safe($meta['page_shadow']);
        }
        if ($mainProps) {
            $css .= "main[role=main]{" . implode(';', $mainProps) . "}\n";
        }

        // Body background
        $bodyProps = [];
        if (!empty($meta['page_bg_color']) && $meta['page_bg_color'] !== '#ffffff') {
            $bodyProps[] = "background-color:" . $safe($meta['page_bg_color']);
        }
        if ($bodyProps) {
            $css .= "body{" . implode(';', $bodyProps) . "}\n";
        }

        // Background image via pseudo-element
        if (!empty($meta['page_bg_image'])) {
            $src = $meta['page_bg_image'];
            if (preg_match('#^(https?://|/[^/])#', $src)) {
                $opacity = max(0, min(1, (float) ($meta['page_bg_opacity'] ?? 1)));
                $size = preg_replace('/[^a-z]/', '', $meta['page_bg_size'] ?? 'cover');
                $pos = preg_replace('/[^a-z\s]/', '', $meta['page_bg_position'] ?? 'center');
                $att = preg_replace('/[^a-z]/', '', $meta['page_bg_attachment'] ?? 'scroll');
                $css .= "body::after{content:'';position:fixed;inset:0;z-index:-2;pointer-events:none;";
                $css .= "background-image:url('" . addcslashes($src, "'\\") . "');";
                $css .= "background-size:{$size};background-position:{$pos};background-attachment:{$att};";
                $css .= "opacity:{$opacity}}\n";
            }
        }

        // Gradient overlay
        if (!empty($meta['page_gradient_enabled'])) {
            $from = $safe($meta['page_gradient_from'] ?? '#000000');
            $to = $safe($meta['page_gradient_to'] ?? '#ffffff');
            $opacity = max(0, min(1, (float) ($meta['page_gradient_opacity'] ?? 0.5)));
            $css .= "body::before{content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;";
            $css .= "background:linear-gradient(to bottom,{$from},{$to});opacity:{$opacity}}\n";
        }

        return $css;
    }
}
