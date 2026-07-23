<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Grid\Services\GridResolver;
use App\Domain\Hooks\HookDispatcher;
use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Support\CanvasBounds;
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

    /** slider_ref height override, consumed by the next slider root render */
    private array $sliderHeightOverride = [];

    /** cycle guard: slider ids currently being inlined */
    private array $renderingSliders = [];

    /** cycle guard: global-section ids currently being inlined */
    private array $renderingGlobalSections = [];

    /** per-build style-preset resolver (lazy; caches presets for this build) */
    private ?\App\Domain\Blocks\Services\StylePresetResolver $presetResolver = null;

    private function presetResolver(): \App\Domain\Blocks\Services\StylePresetResolver
    {
        return $this->presetResolver ??= new \App\Domain\Blocks\Services\StylePresetResolver();
    }

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

        // A page whose raw_html is a COMPLETE document (exact-copy site
        // import) publishes verbatim: it ships its own <head>, styles, nav,
        // and scripts, so the theme wrapper, menus, and token CSS must not
        // touch it. Body-fragment raw_html keeps the normal path below.
        if ($content instanceof Page && self::isDocumentPage($content)) {
            $html = $this->hooks->applyFilter('page_render', $content->raw_html, $content, $site);
            if (!$this->isPreview) {
                $html = SiteFilesPublisher::rewriteHtml($html, $site);
                $html = AssetPublisher::rewriteHtml($html);
                $html = self::rewriteBaseForSlugHosting($html, $site);
            }

            return $this->minifier->minify($html);
        }

        $headContent = $this->seoService->generatePageHead($content, $site);
        $themeConfig = $theme?->config ?? [];

        // Script injection
        $settings = $site->settings ?? [];

        // Exact-copy design sites publish BARE: the imported package's own
        // CSS/JS is the ONLY styling. The design CSS is typically a compiled
        // Tailwind sheet living inside @layer blocks — ANY un-layered wrapper
        // CSS (tokens, critical CSS, mobile overrides) would beat it in the
        // cascade regardless of specificity, so it must not be emitted at all.
        $bareDesign = ($settings['design_fidelity'] ?? null) === 'exact';
        $headScripts = ($settings['head_scripts'] ?? '') . ($content->seo_meta['head_scripts'] ?? '');
        $bodyScripts = ($settings['body_scripts'] ?? '') . ($content->seo_meta['body_scripts'] ?? '');
        $customCss = ($settings['custom_css'] ?? '') . ($content->seo_meta['custom_css'] ?? '');

        // Page appearance CSS from seo_meta
        $pageMeta = $content->seo_meta ?? [];
        $pageAppearanceCss = $this->buildPageAppearanceCss($pageMeta);
        if ($pageAppearanceCss) $customCss .= "\n" . $pageAppearanceCss;

        // Slider embeds: publish the hashed motion-runtime + SELF-HOSTED
        // Swiper/GSAP next to the static output (tenant CSPs commonly restrict
        // script-src to 'self' — no third-party CDN). Deferred, order
        // preserved. Pages without sliders load nothing.
        if ($content->blocks()->where('type', 'slider_ref')->exists()) {
            $runtime = \App\Support\Blocks\SliderRender::publishRuntime($site);
            $headScripts .= "\n" . '<link rel="stylesheet" href="' . $runtime['swiperCss'] . '">'
                . "\n" . '<link rel="stylesheet" href="' . $runtime['css'] . '">';
            $bodyScripts .= "\n" . '<script defer src="' . $runtime['swiperJs'] . '"></script>'
                . "\n" . '<script defer src="' . $runtime['gsapJs'] . '"></script>'
                . "\n" . '<script defer src="' . $runtime['js'] . '"></script>';
        }

        // Collections search islands (Track G2): pages carrying any search
        // block load the hashed vanilla-JS island runtime; everything else
        // loads nothing.
        if ($content->blocks()->whereIn('type', ['search-box', 'facet-filter', 'results-grid'])->exists()) {
            $bodyScripts .= "\n" . \App\Domain\Collections\Services\CollectionPublishService::publishSearchRuntime($site);
        }

        // Interactive app-blocks (breathing pacer, meditation timer, pelvic
        // trainer, partner deck) ship a shared self-hosted JS+CSS runtime —
        // published only on pages that actually contain one of them.
        if ($content->blocks()->whereIn('type', \App\Support\Blocks\AppToolRender::TYPES)->exists()) {
            $appTools = \App\Support\Blocks\AppToolRender::publishRuntime($site);
            $headScripts .= "\n" . '<link rel="stylesheet" href="' . $appTools['css'] . '">';
            $bodyScripts .= "\n" . '<script defer src="' . $appTools['js'] . '"></script>';
        }

        // Experience Mode: inject assets for cinematic pages ONLY
        $experienceMode = $content->experience_mode ?? 'standard';
        if ($experienceMode === 'cinematic') {
            $customCss .= "\n" . '@supports (view-transition-name: none) { @view-transition { navigation: auto; } }';
            // Inject experience runtime CSS + deferred JS (hashed filename busts CDN cache)
            $headScripts .= "\n" . '<link rel="stylesheet" href="/assets/experience/experience-runtime.a44ae8ee.css">';
            // Atmosphere config — pass page-level toggles to the runtime via JSON
            $atmosphere = [
                'preloader' => !empty($pageMeta['experience_preloader']),
                'cursor' => !empty($pageMeta['experience_cursor']),
                'sound' => !empty($pageMeta['experience_sound']),
                'soundAsset' => $pageMeta['experience_sound_asset'] ?? null,
            ];
            $bodyScripts .= "\n" . '<script id="experience-config" type="application/json">' . json_encode($atmosphere) . '</script>';
            $bodyScripts .= "\n" . '<script defer src="/assets/experience/experience-runtime.a44ae8ee.js"></script>';
        }

        // Global custom cursor (site-level setting, all pages)
        if (!empty($settings['cursor_enabled'])) {
            $cursorConfig = json_encode([
                'enabled' => true,
                'preset' => in_array($settings['cursor_preset'] ?? '', ['dot-ring', 'circle-dot', 'minimal', 'crosshair', 'ring-only', 'glow', 'spotlight', 'dash-ring', 'square', 'arrow-dot']) ? $settings['cursor_preset'] : 'dot-ring',
                'color' => \App\Support\Blocks\BlockStyle::safeColor($settings['cursor_color'] ?? '') ?: 'var(--color-text, #201F1D)',
                'ringColor' => \App\Support\Blocks\BlockStyle::safeColor($settings['cursor_ring_color'] ?? '') ?: 'var(--color-text-muted, #7D7B7A)',
                'blend' => in_array($settings['cursor_blend'] ?? '', ['normal', 'difference', 'exclusion']) ? $settings['cursor_blend'] : 'normal',
                'size' => in_array($settings['cursor_size'] ?? '', ['sm', 'md', 'lg']) ? $settings['cursor_size'] : 'md',
            ]);
            $cursorHash = substr(md5_file(resource_path('js/site-cursor.js')), 0, 8);
            $bodyScripts .= "\n" . '<script id="cursor-config" type="application/json">' . $cursorConfig . '</script>';
            $bodyScripts .= "\n" . '<script defer src="/assets/site-cursor.' . $cursorHash . '.js"></script>';
        }

        $criticalCss = $bareDesign ? '' : $this->buildCriticalCss($themeConfig);
        $fontPreloads = $bareDesign ? '' : $this->buildFontPreloads($themeConfig);
        $rssUrl = $site->publicBaseUrl() . '/feed.xml';

        // Design tokens CSS
        $designTokensCss = $bareDesign ? '' : $this->tokenGenerator->generate($site);

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
                'cssFile' => $bareDesign ? null : ($themeConfig['css_file'] ?? null),
                'navigation' => '',
                'footerNavigation' => '',
                'renderedBlocks' => $bodyContent,
                'bareWrapper' => $bareDesign,
                'designTokensCss' => $designTokensCss ?? '',
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'content' => $content,
                'themeConfig' => $themeConfig,
                'lang' => $content->seo_meta['locale'] ?? $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
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

        // Try grid-based rendering (only if no explicit layout override).
        // A page carrying raw_html is an explicit document (imported apps,
        // custom HTML) — a site-default grid must not swallow it. Same for
        // exact-fidelity design sites: every page is self-contained, so the
        // theme's grid scaffolding must not wrap it.
        $hasRawHtml = $content instanceof Page && $content->raw_html;
        $grid = (!$useLayout && !$hasRawHtml && !$bareDesign) ? $this->gridResolver->resolve($content, $site) : null;

        if ($grid) {
            $gridResult = $this->gridRenderer->render($grid, $content, $site);

            $html = View::make('publishing.grid-layout', [
                'headContent' => $headContent,
                'headScripts' => $headScripts,
                'bodyScripts' => $bodyScripts,
                'customCss' => $customCss,
                'criticalCss' => $criticalCss,
                'fontPreloads' => $fontPreloads,
                'cssFile' => $bareDesign ? null : ($themeConfig['css_file'] ?? null),
                'gridCss' => $gridResult['css'],
                'gridHtml' => $gridResult['html'],
                'designTokensCss' => $designTokensCss,
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'lang' => $content->seo_meta['locale'] ?? $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
            ])->render();
        } else {
            // Check for theme template (posts only)
            $template = ($content instanceof Post)
                ? ThemeTemplate::resolveForPost($content)
                : null;

            if ($template) {
                $renderedBlocks = $this->renderTemplatedPost($content, $template, $site);
            } elseif ($content->editor_mode === 'canvas') {
                // Canvas editor mode: a vertical stack of Section canvases with
                // absolutely-positioned block children (theme-width, mobile
                // auto-stack). Reuses the same header/footer/layout wrapping below.
                $renderedBlocks = $this->renderCanvasPage($content, $site);
            } else {
                // Standard: render content's own blocks
                $blocks = $content->blocks()
                    ->whereNull('parent_block_id')
                    ->orderBy('order')
                    ->with('children')
                    ->get();

                $renderedBlocks = '';
                foreach ($blocks as $block) {
                    // Isolate per-block render failures: one fragile block must
                    // not fail the whole page publish. Emit a placeholder + log.
                    try {
                        $renderedBlocks .= $this->renderBlock($block, $site);
                    } catch (\Throwable $e) {
                        logger()->warning("Block render failed (type={$block->type}, id={$block->id}): {$e->getMessage()}");
                        $renderedBlocks .= "<!-- block render failed: {$block->type} -->";
                    }
                }

                // Append raw HTML content (preserved verbatim — scripts, embeds, custom HTML)
                if ($content instanceof Page && $content->raw_html) {
                    $renderedBlocks .= $content->raw_html;
                }
            }

            // Posts publish inside an <article> landmark (F3 — semantic extraction);
            // covers both the standard layout and wrapper paths.
            if ($content instanceof Post) {
                $renderedBlocks = '<article>' . $renderedBlocks . '</article>';
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
                'cssFile' => $bareDesign ? null : ($themeConfig['css_file'] ?? null),
                'navigation' => ($layoutSlug === 'standard' && !$bareDesign) ? ($headerHtml ?? '') : '',
                'footerNavigation' => ($layoutSlug === 'standard' && !$bareDesign) ? ($footerHtml ?? '') : '',
                'renderedBlocks' => $bodyContent,
                'bareWrapper' => $bareDesign,
                'mainStyle' => ($layoutSlug === 'standard' && !$bareDesign) ? 'max-width:var(--container-width, 1200px);margin:0 auto;padding:0 var(--container-padding, 24px);' : '',
                'designTokensCss' => $designTokensCss ?? '',
                'hookHeadScripts' => $hookHeadScripts,
                'hookBodyOpen' => $hookBodyOpen,
                'hookBodyClose' => $hookBodyClose,
                'site' => $site,
                'rssUrl' => $rssUrl,
                'content' => $content,
                'themeConfig' => $themeConfig,
                'lang' => $content->seo_meta['locale'] ?? $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
            ])->render();
        }

        // Apply page_render filter hook
        $html = $this->hooks->applyFilter('page_render', $html, $content, $site);

        // Rewrite all /api/v1/.../assets/.../serve URLs to static public paths
        // This ensures images and files work on the static site without the backend running
        // Skip during preview — API serve URLs work with auth cookie on the admin domain
        if (!$this->isPreview) {
            // Block pages can carry design-package file references too (e.g.
            // exact-copy pages rebuilt as html-embed block trees).
            $html = SiteFilesPublisher::rewriteHtml($html, $site);
            $html = AssetPublisher::rewriteHtml($html);
            $html = self::rewriteBaseForSlugHosting($html, $site);
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

        $childBlocks = $block->children()->orderBy('order')->get();
        $slideIndex = 0;
        $slideTotal = $block->type === 'slider' ? $childBlocks->where('type', 'slide')->count() : 0;
        foreach ($childBlocks as $child) {
            // slides need their position for eager/lazy media + aria labels
            if ($block->type === 'slider' && $child->type === 'slide') {
                $childData = $child->data ?? [];
                $childData['_slide_index'] = $slideIndex++;
                $childData['_slide_total'] = $slideTotal;
                $childData['_block_id'] = $child->id;
                $child->data = $childData;
            }
            $rendered = $this->renderBlock($child, $site);
            // layers inside a slide get their absolutely-positioned final-state wrapper
            if ($block->type === 'slide') {
                $rendered = \App\Support\Blocks\SliderRender::wrapLayer($child, $rendered);
            }
            $childrenHtml .= $rendered;
            $childrenArray[] = $rendered;
        }

        // Enrich image blocks with asset data
        if ($block->type === 'image') {
            $sanitizedData = $this->enrichImageData($sanitizedData, $site);
        }

        // Enrich gallery images — intrinsic dimensions + library alt (F3)
        if ($block->type === 'gallery') {
            $sanitizedData = $this->enrichGalleryData($sanitizedData);
        }

        // Enrich flipbook blocks — resolve PDF asset to a public URL
        if ($block->type === 'flipbook' && !empty($sanitizedData['pdf_asset_id'])) {
            $sanitizedData = $this->enrichFlipbookData($sanitizedData, $site);
        }

        // Slider system enrichment
        if ($block->type === 'slide') {
            $sanitizedData = $this->enrichSlideData($sanitizedData, $site);
        }
        if ($block->type === 'slider') {
            $sanitizedData['_config'] = \App\Support\Blocks\SliderRender::buildConfig(
                $block,
                $this->sliderHeightOverride,
            );
            $this->sliderHeightOverride = [];
        }
        if ($block->type === 'slider_ref') {
            $sanitizedData = $this->enrichSliderRef($sanitizedData, $site);
        }
        if ($block->type === 'global_ref') {
            $sanitizedData = $this->enrichGlobalRef($sanitizedData, $site);
        }

        $viewName = "blocks.{$block->type}";
        if (!View::exists($viewName)) {
            return "<!-- Unknown block type: {$block->type} -->";
        }

        // Extract shared properties stored in data.__style, __animation, __advanced
        $localStyle = $block->style ?? $sanitizedData['__style'] ?? [];
        // P3: resolve linked style presets (element → option-groups → local overrides).
        // No-op (returns local unchanged) when the block links no preset.
        $blockStyle = $this->presetResolver()->resolve(
            $site, is_array($localStyle) ? $localStyle : [], $sanitizedData,
        );
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
     * Canvas editor render (Phase 1). A canvas page is a vertical stack of
     * Section canvases; each section is a positioning context whose child
     * blocks are placed freeform via style.layout {x,y,width,height,rotation,
     * zIndex}. Width = theme content width (per-page overridable). Below the
     * design width the whole thing auto-stacks into reading order (children are
     * already emitted sorted by y,x so the source order is correct for SEO/a11y;
     * CSS just drops absolute positioning).
     */
    private function renderCanvasPage(Page|Post $content, Site $site): string
    {
        $meta = is_array($content->seo_meta) ? $content->seo_meta : [];
        $cfg = $meta['canvas'] ?? [];
        $canvasWidth = CanvasBounds::canvasWidth((int) ($cfg['width'] ?? CanvasBounds::CANVAS_W_DEFAULT));
        $mobileWidth = CanvasBounds::mobileWidth((int) ($cfg['mobile_width'] ?? CanvasBounds::MOBILE_W_DEFAULT));
        $isSingle = (($cfg['page_type'] ?? 'website') === 'single');

        $sections = $content->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with(['children' => fn ($q) => $q->orderBy('order')])
            ->get();

        // Per-breakpoint overrides for phone width are collected per section and
        // emitted in one ≤767px media query AFTER the tablet-stack rule so the
        // custom mobile layout wins (id/2-class selectors beat the stack rules).
        $mobileCss = '';
        $hasAnim = false;
        $out = '';
        foreach ($sections as $section) {
            $out .= $this->renderCanvasSection($section, $site, $canvasWidth, $mobileWidth, $mobileCss, $hasAnim);
            if ($isSingle) {
                break; // single: one fixed canvas, no scroll/stack
            }
        }

        $css = '<style>'
            . '.cv-page{--cv-w:' . $canvasWidth . 'px}'
            . '.cv-section{position:relative;margin-left:auto;margin-right:auto;width:var(--cv-w);max-width:100%}'
            // Fluid sections flex with the viewport (capped at the design width);
            // elements hold their pin anchors instead of stacking.
            . '.cv-fluid{width:100%!important;max-width:var(--cv-w)!important;margin-left:auto;margin-right:auto}'
            . '.cv-bleed{position:relative;width:100%}'
            . '.cv-el{position:absolute;box-sizing:border-box}'
            . '.cv-anim{display:block;width:100%;height:100%}'
            . '.cv-el>*,.cv-anim>*{width:100%;height:100%}'
            // Scroll-reveal: paused until in view (only once JS marks the page
            // ready — no-JS falls back to an on-load entrance); honor reduced-motion.
            . '.cv-page.cv-ready .cv-anim{animation-play-state:paused}'
            . '.cv-page.cv-ready .cv-anim.cv-in{animation-play-state:running}'
            . '@media(prefers-reduced-motion:reduce){.cv-anim{animation:none!important}}'
            // Zone 2 — tablet/below-design-width: auto-stack (non-fluid only).
            . '@media(max-width:' . $canvasWidth . 'px){'
            . '.cv-section:not(.cv-fluid){width:100%!important;height:auto!important;min-height:0!important}'
            . '.cv-bleed .cv-section:not(.cv-fluid){padding-left:1rem;padding-right:1rem}'
            . '.cv-section:not(.cv-fluid) .cv-el{position:static!important;width:100%!important;height:auto!important;'
            . 'left:auto!important;top:auto!important;transform:none!important;margin:0 0 1.25rem 0}'
            . '.cv-section:not(.cv-fluid) .cv-anim{height:auto!important}'
            . '.cv-section:not(.cv-fluid) .cv-el>*,.cv-section:not(.cv-fluid) .cv-anim>*{height:auto}'
            . '}'
            // Zone 3 — phone: sections with a custom mobile layout un-stack.
            . ($mobileCss !== '' ? '@media(max-width:767px){' . $mobileCss . '}' : '')
            . '</style>';

        // ~12-line vanilla reveal observer, emitted only when animations are used.
        $script = $hasAnim
            ? '<script>(function(){var p=document.currentScript.closest(".cv-page")||document.querySelector(".cv-page");if(!p)return;p.classList.add("cv-ready");'
                . 'if(!("IntersectionObserver" in window)){p.querySelectorAll("[data-cv-anim]").forEach(function(e){e.classList.add("cv-in")});return;}'
                . 'var o=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add("cv-in");o.unobserve(e.target);}});},{threshold:0.12});'
                . 'p.querySelectorAll("[data-cv-anim]").forEach(function(e){o.observe(e);});})();</script>'
            : '';

        return '<div class="cv-page">' . $css . $out . $script . '</div>';
    }

    private function renderCanvasSection(Block $section, Site $site, int $canvasWidth, int $mobileWidth, string &$mobileCss, bool &$hasAnim): string
    {
        $data = is_array($section->data) ? $section->data : [];
        $canvas = $data['canvas'] ?? [];
        $bleed = ! empty($canvas['bleed']);
        $fluid = ! empty($canvas['fluid']);
        $bg = BlockStyle::safeColor($canvas['background'] ?? ($data['bg_color'] ?? ''));
        $bgStyle = $bg ? "background:{$bg};" : '';

        // Desktop markup order = reading order (y, then x).
        $children = $section->children->sortBy(function ($c) {
            $l = $this->childLayout($c);
            return sprintf('%08d-%08d', $l['y'] + 100000, $l['x'] + 100000);
        })->values();

        $heightSetting = $canvas['height'] ?? 'auto';
        $maxBottom = 0;
        foreach ($children as $c) {
            $l = $this->childLayout($c);
            $maxBottom = max($maxBottom, $l['y'] + $l['h']);
        }
        $sectionH = is_numeric($heightSetting) ? max(1, (int) $heightSetting) : ($maxBottom > 0 ? $maxBottom : 200);

        $sid = 'cvs-' . preg_replace('/[^a-z0-9\-]/i', '', (string) $section->id);
        $hasMobile = false;
        $mobRules = '';
        $mobBottom = 0;

        $els = '';
        foreach ($children as $child) {
            $l = $this->childLayout($child);
            $eid = 'cve-' . preg_replace('/[^a-z0-9\-]/i', '', (string) $child->id);
            try {
                $inner = $this->renderBlock($child, $site);
            } catch (\Throwable $e) {
                logger()->warning("Canvas element render failed (type={$child->type}, id={$child->id}): {$e->getMessage()}");
                $inner = "<!-- canvas element failed: {$child->type} -->";
            }
            $t = $l['rotation'] !== 0.0 ? "transform:rotate({$l['rotation']}deg);" : '';
            $z = $l['zIndex'] !== 0 ? "z-index:{$l['zIndex']};" : '';
            // Scroll-triggered entrance animation wraps the block (separate from
            // the cv-el rotate transform so the two never fight).
            $animCss = $this->childAnimCss($child);
            if ($animCss !== '') {
                $hasAnim = true;
                $inner = '<div class="cv-anim" data-cv-anim style="' . $animCss . '">' . $inner . '</div>';
            }

            // Fluid sections position by pin anchor; others use plain left/width.
            $pos = $fluid
                ? $this->pinHorizontalCss($l, $canvasWidth) . "top:{$l['y']}px;height:{$l['h']}px;"
                : "left:{$l['x']}px;top:{$l['y']}px;width:{$l['w']}px;height:{$l['h']}px;";
            $els .= '<div class="cv-el" id="' . $eid . '" style="'
                . $pos . $t . $z
                . '">' . $inner . '</div>';

            // Mobile override for this element (base merged with layout.bp.mobile).
            // Resets pin props (right/margin) so the phone layout is fully absolute.
            $m = $this->childMobile($child, $l);
            if ($m !== null) {
                $hasMobile = true;
                if ($m['hidden']) {
                    $mobRules .= "#{$eid}{display:none!important}";
                } else {
                    $mt = "transform:rotate({$m['rotation']}deg)!important;";
                    $mobRules .= "#{$eid}{left:{$m['x']}px!important;top:{$m['y']}px!important;right:auto!important;margin-left:0!important;"
                        . "width:{$m['w']}px!important;height:{$m['h']}px!important;{$mt}z-index:{$m['zIndex']}!important}";
                    $mobBottom = max($mobBottom, $m['y'] + $m['h']);
                }
            }
        }

        $fluidClass = $fluid ? ' cv-fluid' : '';
        $mobClass = '';
        if ($hasMobile) {
            $mobClass = ' cv-mob ' . $sid;
            $mobH = $mobBottom > 0 ? $mobBottom : 200;
            $mobileCss .= ".{$sid}{width:{$mobileWidth}px!important;max-width:100%!important;height:{$mobH}px!important;"
                . 'margin-left:auto!important;margin-right:auto!important;padding-left:0!important;padding-right:0!important}'
                . ".{$sid} .cv-el{position:absolute!important;width:auto;height:auto;margin:0!important}"
                . $mobRules;
        }

        if ($bleed) {
            return '<section class="cv-bleed" style="' . $bgStyle . '">'
                . '<div class="cv-section' . $fluidClass . $mobClass . '" style="height:' . $sectionH . 'px">' . $els . '</div>'
                . '</section>';
        }

        return '<section class="cv-section' . $fluidClass . $mobClass . '" style="height:' . $sectionH . 'px;' . $bgStyle . '">' . $els . '</section>';
    }

    /** Entrance-animation CSS for a canvas element, or '' when none/invalid. */
    private function childAnimCss(Block $child): string
    {
        $style = is_array($child->style) ? $child->style : (($child->data['__style'] ?? []) ?: []);
        $lay = is_array($style['layout'] ?? null) ? $style['layout'] : [];
        $anim = $lay['anim'] ?? null;
        if (! is_array($anim)) {
            return '';
        }
        $map = [
            'fade' => 'block-fade', 'slide-up' => 'block-slide-up', 'slide-down' => 'block-slide-down',
            'slide-left' => 'block-slide-left', 'slide-right' => 'block-slide-right',
            'zoom' => 'block-zoom', 'scale-in' => 'block-scale-in',
        ];
        $name = $map[$anim['type'] ?? 'none'] ?? null;
        if ($name === null) {
            return '';
        }
        $dur = max(50, min(3000, (int) ($anim['duration'] ?? 600)));
        $del = max(0, min(5000, (int) ($anim['delay'] ?? 0)));

        return "animation-name:{$name};animation-duration:{$dur}ms;animation-delay:{$del}ms;animation-timing-function:ease-out;animation-fill-mode:both;";
    }

    /**
     * Effective phone layout for a child (base merged with style.layout.bp.mobile),
     * or null when the element has no mobile override.
     */
    private function childMobile(Block $child, array $base): ?array
    {
        $style = is_array($child->style) ? $child->style : (($child->data['__style'] ?? []) ?: []);
        $lay = is_array($style['layout'] ?? null) ? $style['layout'] : [];
        $bp = $lay['bp']['mobile'] ?? null;
        if (! is_array($bp) || $bp === []) {
            return null;
        }

        // Each axis: the mobile override if present, else inherit the (already
        // clamped) base — then re-clamp via the shared bounds.
        $m = CanvasBounds::clampBox(
            isset($bp['x']) ? $this->layoutPx($bp['x'], $base['x']) : $base['x'],
            isset($bp['y']) ? $this->layoutPx($bp['y'], $base['y']) : $base['y'],
            isset($bp['width']) ? $this->layoutPx($bp['width'], $base['w']) : $base['w'],
            isset($bp['height']) ? $this->layoutPx($bp['height'], $base['h']) : $base['h'],
            isset($bp['rotation']) ? (float) $bp['rotation'] : $base['rotation'],
            isset($bp['zIndex']) ? (int) $bp['zIndex'] : $base['zIndex'],
        );
        $m['hidden'] = ! empty($bp['hidden']);

        return $m;
    }

    /** Sanitized freeform layout for a canvas child block (from its style.layout). */
    private function childLayout(Block $child): array
    {
        $style = is_array($child->style) ? $child->style : (($child->data['__style'] ?? []) ?: []);
        $lay = is_array($style['layout'] ?? null) ? $style['layout'] : [];

        $pin = $lay['pinX'] ?? 'left';
        if (! in_array($pin, ['left', 'center', 'right', 'stretch'], true)) {
            $pin = 'left';
        }

        $l = CanvasBounds::clampBox(
            $this->layoutPx($lay['x'] ?? 0, 0),
            $this->layoutPx($lay['y'] ?? 0, 0),
            $this->layoutPx($lay['width'] ?? 200, 200),
            $this->layoutPx($lay['height'] ?? 100, 100),
            (float) ($lay['rotation'] ?? 0),
            (int) ($lay['zIndex'] ?? 0),
        );
        $l['pinX'] = $pin;

        return $l;
    }

    /** Parse a raw layout value (strips px/units) to an int; empty → default. */
    private function layoutPx(mixed $v, int|float $def): int
    {
        return (int) round((float) preg_replace('/[^0-9.\-]/', '', (string) ($v ?? $def)) ?: $def);
    }

    /**
     * Horizontal CSS for a canvas element by its pin anchor, relative to the
     * design width — so in a fluid section it holds that edge as the container
     * flexes. Vertical is always top-anchored by the caller.
     */
    private function pinHorizontalCss(array $l, int $canvasWidth): string
    {
        $x = $l['x'];
        $w = $l['w'];
        $rInset = $canvasWidth - ($x + $w);
        return match ($l['pinX']) {
            'right' => "right:{$rInset}px;width:{$w}px;",
            'center' => 'left:50%;margin-left:' . ($x - intdiv($canvasWidth, 2)) . "px;width:{$w}px;",
            'stretch' => "left:{$x}px;right:{$rInset}px;",
            default => "left:{$x}px;width:{$w}px;",
        };
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

                // Alt text falls back to the media library's alt (F3)
                if (empty($data['alt']) && $asset->alt_text) {
                    $data['alt'] = $asset->alt_text;
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
     * Resolve gallery image URLs back to their assets for intrinsic
     * dimensions (CLS) and library alt text (F3). String items whose URL
     * matches the asset serve route become arrays the Blade already handles;
     * anything else passes through untouched.
     */
    private function enrichGalleryData(array $data): array
    {
        $images = $data['images'] ?? [];
        foreach ($images as $i => $img) {
            $src = is_array($img) ? ($img['src'] ?? $img['url'] ?? '') : (string) $img;
            if ($src === '' || !preg_match('#/assets/([0-9a-f-]{36})/serve#i', $src, $m)) {
                continue;
            }
            $asset = Asset::find($m[1]);
            if (!$asset) {
                continue;
            }
            $item = is_array($img) ? $img : ['src' => $src];
            if (empty($item['alt']) && $asset->alt_text) {
                $item['alt'] = $asset->alt_text;
            }
            if (empty($item['width']) && !empty($asset->dimensions['width'])) {
                $item['width'] = $asset->dimensions['width'];
                $item['height'] = $asset->dimensions['height'] ?? null;
            }
            $images[$i] = $item;
        }
        $data['images'] = $images;

        return $data;
    }

    /**
     * Resolve flipbook PDF asset to a publicly accessible URL.
     * Copies the PDF to public_html so it's served statically.
     */
    /**
     * Resolve a slide's background asset to a static URL and re-validate the
     * overlay pattern on the way out (defense in depth for old rows).
     */
    private function enrichSlideData(array $data, Site $site): array
    {
        $bg = $data['background'] ?? [];
        $data['_bg_url'] = \App\Support\Blocks\SliderRender::resolveBackgroundUrl($bg, $site->id);

        $overlay = $bg['overlay'] ?? null;
        $data['_overlay_css'] = (is_string($overlay) && preg_match(
            '/^(rgba?\([\d\s.,%]+\)|#[0-9a-fA-F]{3,8}|(linear|radial)-gradient\([^;{}<>]{1,250}\))$/',
            $overlay,
        )) ? $overlay : null;

        return $data;
    }

    /**
     * Inline the referenced slider's PUBLISHED block tree into the page output
     * (static pages stay self-contained — no runtime lookups). Unpublished or
     * missing sliders render as an HTML comment.
     */
    private function enrichSliderRef(array $data, Site $site): array
    {
        $sliderId = $data['sliderId'] ?? null;
        if (!$sliderId || isset($this->renderingSliders[$sliderId])) {
            return $data;
        }

        $slider = \App\Models\Slider::where('site_id', $site->id)
            ->where('status', 'published')
            ->find($sliderId);
        if (!$slider) {
            return $data;
        }

        $rootBlock = $slider->root_block_id
            ? Block::find($slider->root_block_id)
            : $slider->blocks()->where('type', 'slider')->whereNull('parent_block_id')->first();
        if (!$rootBlock || $rootBlock->type !== 'slider') {
            return $data;
        }

        $this->renderingSliders[$sliderId] = true;
        $this->sliderHeightOverride = is_array($data['heightOverride'] ?? null) ? $data['heightOverride'] : [];
        try {
            $data['_slider_html'] = $this->renderBlock($rootBlock, $site);
        } finally {
            unset($this->renderingSliders[$sliderId]);
            $this->sliderHeightOverride = [];
        }

        return $data;
    }

    /**
     * Inline a Global Section's PUBLISHED block tree into the page (P2). Unlike
     * a slider (single 'slider' root), a global section is just a chunk of
     * blocks — render every top-level block, in order, concatenated. Cycle-
     * guarded (a global embedding itself/another that loops back). Missing or
     * unpublished sections render as an HTML comment.
     */
    private function enrichGlobalRef(array $data, Site $site): array
    {
        $sectionId = $data['sectionId'] ?? null;
        if (!$sectionId || isset($this->renderingGlobalSections[$sectionId])) {
            return $data;
        }

        $section = \App\Models\GlobalSection::where('site_id', $site->id)
            ->where('status', 'published')
            ->find($sectionId);
        if (!$section) {
            return $data;
        }

        $roots = Block::where('blockable_type', 'global_section')
            ->where('blockable_id', $section->id)
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->get();
        if ($roots->isEmpty()) {
            return $data;
        }

        $this->renderingGlobalSections[$sectionId] = true;
        try {
            $html = '';
            foreach ($roots as $root) {
                $html .= $this->renderBlock($root, $site);
            }
            $data['_global_html'] = $html;
        } finally {
            unset($this->renderingGlobalSections[$sectionId]);
        }

        return $data;
    }

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

    /**
     * Sites without a custom domain deploy into a subdirectory of the tenant
     * docroot (ensodo.eu/{slug}/), so every root-absolute URL the build emits
     * — internal links, /assets/files images, srcsets, og tags, CSS url() —
     * escapes the site and 404s (or hits another site). Prefix them with the
     * site slug. Skips /api/ (backend, host-level) and protocol-relative //.
     * Custom-domain sites deploy to their own docroot and need no prefix.
     */
    /** True when the page's raw_html is a full standalone HTML document. */
    public static function isDocumentPage(Page $page): bool
    {
        return $page->raw_html && preg_match('/^\s*(?:<!doctype\b|<html\b)/i', $page->raw_html) === 1;
    }

    public static function rewriteBaseForSlugHosting(string $html, Site $site): string
    {
        if ($site->custom_domain) {
            return $html;
        }
        $base = '/' . trim($site->deploySlug(), '/');

        // href/src/content/action/poster/data-src attributes with root-absolute values
        $html = preg_replace_callback(
            '#\b(href|src|content|action|poster|data-src)="(/[^"/][^"]*|/)"#i',
            function ($m) use ($base) {
                $url = $m[2];
                if (str_starts_with($url, '/api/') || str_starts_with($url, $base . '/') || $url === $base) {
                    return $m[0];
                }
                return "{$m[1]}=\"{$base}{$url}\"";
            },
            $html
        );

        // srcset: comma-separated "url descriptor" pairs
        $html = preg_replace_callback('#\bsrcset="([^"]+)"#i', function ($m) use ($base) {
            $parts = array_map(function ($entry) use ($base) {
                $entry = trim($entry);
                if ($entry !== '' && $entry[0] === '/' && !str_starts_with($entry, '//')
                    && !str_starts_with($entry, '/api/') && !str_starts_with($entry, $base . '/')) {
                    return $base . $entry;
                }
                return $entry;
            }, explode(',', $m[1]));
            return 'srcset="' . implode(', ', $parts) . '"';
        }, $html);

        // CSS url(/...) in inline styles/style tags. Style attributes are
        // HTML-escaped by Blade, so the quote may appear as &#039; / &quot;.
        $html = preg_replace_callback(
            "#url\\((&\\#0?39;|&quot;|['\"])?(/[^'\")&]+)(&\\#0?39;|&quot;|['\"])?\\)#i",
            function ($m) use ($base) {
                $url = $m[2];
                if (str_starts_with($url, '//') || str_starts_with($url, '/api/') || str_starts_with($url, $base . '/')) {
                    return $m[0];
                }
                return 'url(' . ($m[1] ?? '') . $base . $url . ($m[3] ?? '') . ')';
            },
            $html
        );

        return $html;
    }
}
