<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Grid\Services\GridResolver;
use App\Domain\Hooks\HookDispatcher;
use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Support\Blocks\BlockStyle;
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

        // Experience Mode: inject assets for cinematic pages ONLY
        $experienceMode = $content->experience_mode ?? 'standard';
        if ($experienceMode === 'cinematic') {
            $customCss .= "\n" . '@supports (view-transition-name: none) { @view-transition { navigation: auto; } }';
            // Inject experience runtime CSS + deferred JS (hashed filename busts CDN cache)
            $headScripts .= "\n" . '<link rel="stylesheet" href="/assets/experience/experience-runtime.cb000049.css">';
            // Atmosphere config — pass page-level toggles to the runtime via JSON
            $atmosphere = [
                'preloader' => !empty($pageMeta['experience_preloader']),
                'cursor' => !empty($pageMeta['experience_cursor']),
                'sound' => !empty($pageMeta['experience_sound']),
                'soundAsset' => $pageMeta['experience_sound_asset'] ?? null,
            ];
            $bodyScripts .= "\n" . '<script id="experience-config" type="application/json">' . json_encode($atmosphere) . '</script>';
            $bodyScripts .= "\n" . '<script defer src="/assets/experience/experience-runtime.cb000049.js"></script>';
        }

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
                'lang' => $content->seo_meta['locale'] ?? $themeConfig['lang'] ?? 'en',
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
                'lang' => $content->seo_meta['locale'] ?? $themeConfig['lang'] ?? 'en',
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
                'lang' => $content->seo_meta['locale'] ?? $themeConfig['lang'] ?? 'en',
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
        // html-embed blocks are intentionally raw — skip sanitization
        $sanitizedData = $block->type === 'html-embed'
            ? ($block->data ?? [])
            : $this->sanitizer->sanitizeBlock($block);
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
            // Default minimal critical CSS for common blocks — uses theme CSS variables
            $css = '
*,*::before,*::after{box-sizing:border-box}
html,body{overflow-x:hidden;max-width:100vw}
body{margin:0;font-family:var(--font-body,system-ui,-apple-system,sans-serif);font-size:var(--font-size-base,1rem);line-height:var(--line-height-body,1.6);letter-spacing:var(--letter-spacing-body,0);color:var(--color-text,#1e293b);background:var(--color-bg,#ffffff)}
a{color:var(--color-link,var(--color-primary,#3b82f6));text-decoration:var(--text-decoration-link,none);transition:color var(--transition-base,250ms ease),opacity var(--transition-base,250ms ease)}
a:hover{color:var(--color-link-hover,var(--color-primary-dark,#2563eb));text-decoration:var(--text-decoration-link-hover,underline);opacity:var(--link-hover-opacity,1)}
img{max-width:100%;height:auto;display:block}
h1,h2,h3,h4,h5,h6{font-family:var(--font-heading,inherit);font-weight:var(--heading-weight,700);letter-spacing:var(--letter-spacing-heading,0);line-height:var(--line-height-heading,1.25);color:var(--color-heading,var(--color-text,#0f172a))}
.hero-section{position:relative;min-height:400px;display:flex;align-items:center;justify-content:center;color:#fff;background-size:cover;background-position:center}
.hero-content{position:relative;z-index:1}
.text-block{margin-bottom:1.5rem}
.prose{max-width:var(--prose-max-width,65ch);margin-left:auto;margin-right:auto}
.prose p{margin:0 0 1em}
.prose h2,.prose h3,.prose h4{margin:1.5em 0 0.5em}
.prose ul,.prose ol{padding-left:1.5em}
.columns-block{margin-bottom:1.5rem}
.image-block{margin-bottom:1.5rem}
.image-block figcaption{font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#666);margin-top:0.5rem;text-align:center}
.quote-block{border-left:4px solid var(--color-primary,#3b82f6);padding:1rem 1.5rem;margin:1.5rem 0;font-style:italic}
.quote-block cite{display:block;font-style:normal;font-weight:600;margin-top:0.5rem}
.divider-block{border:none;border-top:1px solid var(--color-border,#e5e7eb);margin:2rem 0}
.btn{display:inline-block;padding:var(--btn-padding,12px 24px);font-family:var(--font-button,var(--font-body,inherit));font-weight:var(--btn-font-weight,600);font-size:var(--font-size-sm,0.875rem);letter-spacing:var(--btn-tracking,0.12em);text-transform:var(--btn-transform,uppercase);text-decoration:none;border-radius:var(--btn-radius,var(--border-radius-md,8px));transition:all var(--transition-base,250ms ease);cursor:pointer;border:1px solid var(--btn-border,transparent)}
.btn-primary{background:var(--btn-bg,var(--color-primary,#3b82f6));color:var(--btn-color,#ffffff)}
.btn-primary:hover{background:var(--btn-hover-bg,var(--color-primary-dark,#2563eb));color:var(--btn-hover-color,#ffffff);opacity:1}
.btn-outline{background:transparent;color:var(--btn-bg,var(--color-primary,#3b82f6));border-color:var(--btn-bg,var(--color-primary,#3b82f6))}
.btn-outline:hover{background:var(--btn-bg,var(--color-primary,#3b82f6));color:var(--btn-color,#ffffff);opacity:1}
.btn-secondary{background:var(--color-bg-alt,#f8fafc);color:var(--color-text,#1e293b);border-color:var(--color-border,#e2e8f0)}
.btn-secondary:hover{background:var(--color-border,#e2e8f0);opacity:1}
.btn-ghost{background:transparent;color:var(--color-text,#1e293b);border-color:transparent}
.btn-ghost:hover{background:var(--color-bg-alt,#f8fafc);opacity:1}
';
        }

        // Overlay nav mode — full-screen menu overlay (must be in critical CSS for grid-layout pages)
        $css .= '
.site-nav--overlay .nav-toggle,.site-nav--overlay .menu-hamburger{display:flex!important}
.site-nav--overlay .nav-menu,.site-nav--overlay .menu-desktop{display:none!important;position:fixed;top:0;left:0;right:0;bottom:0;flex-direction:column;align-items:center;justify-content:center;gap:var(--nav-overlay-gap,1.5rem)!important;background:var(--nav-overlay-bg,rgba(69,64,48,0.8));backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);z-index:2999;padding:2rem!important;list-style:none;overflow-y:auto}
.site-nav--overlay.nav-open .nav-menu,.site-nav--overlay.menu-open .menu-desktop{display:flex!important}
.site-nav--overlay .nav-menu li,.site-nav--overlay .menu-desktop li{width:auto;list-style:none}
.site-nav--overlay .nav-menu a,.site-nav--overlay .menu-desktop a{display:block;padding:0.5rem 1rem;font-size:var(--nav-overlay-font-size,2rem)!important;font-weight:var(--nav-overlay-font-weight,300)!important;color:var(--nav-overlay-color,#F3F0EA)!important;letter-spacing:var(--nav-overlay-tracking,0.05em)!important;text-transform:var(--nav-overlay-transform,none)!important;border-bottom:none!important;opacity:0.85;transition:opacity 0.3s}
.site-nav--overlay .nav-menu a:hover,.site-nav--overlay .menu-desktop a:hover{opacity:1;background:transparent!important;color:var(--nav-overlay-hover-color,#fff)!important}
.site-nav--overlay.nav-open .nav-toggle,.site-nav--overlay.menu-open .menu-hamburger{position:fixed;top:20px;right:20px;z-index:3001}
.site-nav--overlay.nav-open .nav-toggle-line,.site-nav--overlay.menu-open .menu-hamburger span{background:var(--nav-overlay-color,#F3F0EA)}
.site-nav--overlay .nav-submenu,.site-nav--overlay .submenu{position:static!important;box-shadow:none!important;border:none!important;padding:0!important;background:transparent!important;border-radius:0!important;display:block!important}
.site-nav--overlay .nav-submenu a,.site-nav--overlay .submenu a{font-size:var(--nav-overlay-sub-font-size,1.2rem)!important;color:var(--nav-overlay-color,#F3F0EA)!important;opacity:0.6}
.site-nav--overlay .menu-hamburger-panel{display:none!important}
@media(max-width:768px){.site-nav--overlay .nav-menu a,.site-nav--overlay .menu-desktop a{font-size:1.4rem!important;padding:0.4rem 1rem}.site-nav--overlay .nav-submenu a,.site-nav--overlay .submenu a{font-size:1rem!important}.menu-desktop-links{gap:1rem!important}}
';

        // Footer styling
        $css .= '
footer[role="contentinfo"]{background:var(--footer-bg,var(--color-bg-alt,#f8fafc));color:var(--footer-color,var(--color-text-muted,#64748b));border-top:1px solid var(--footer-border-color,var(--color-border-light,#f0f0eb));padding:2rem 0}
footer[role="contentinfo"] a{color:var(--footer-color,var(--color-text-muted,#64748b));transition:color 0.2s}
footer[role="contentinfo"] a:hover{color:var(--color-primary,#3b82f6);opacity:1}
';

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
        $pageStyle = $meta['pageStyle'] ?? [];
        $pageData = $meta['pageData'] ?? [];

        if (empty($pageStyle) && empty($pageData)) return '';

        // Generate inline CSS using the same BlockStyle engine as blocks
        $style = BlockStyle::buildStyle($pageStyle, [], $pageData);
        if (!$style) return '';

        return "main,.page-content{{$style}}\n";
    }
}
