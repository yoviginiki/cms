<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Support\Facades\View;

class BuildPageService
{
    public function __construct(
        private SanitizationService $sanitizer,
        private SeoService $seoService,
        private HtmlMinifier $minifier,
    ) {}

    public function build(Page|Post $content, ?Theme $theme, Site $site): string
    {
        $blocks = $content->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $renderedBlocks = '';
        foreach ($blocks as $block) {
            $renderedBlocks .= $this->renderBlock($block, $site);
        }

        $headContent = $this->seoService->generatePageHead($content, $site);
        $themeConfig = $theme?->config ?? [];

        // Site-level script injection
        $settings = $site->settings ?? [];
        $headScripts = $settings['head_scripts'] ?? '';
        $bodyScripts = $settings['body_scripts'] ?? '';
        $customCss = $settings['custom_css'] ?? '';

        // Page/post-level script injection
        $seoMeta = $content->seo_meta ?? [];
        $headScripts .= $seoMeta['head_scripts'] ?? '';
        $bodyScripts .= $seoMeta['body_scripts'] ?? '';
        $customCss .= $seoMeta['custom_css'] ?? '';

        $html = View::make('publishing.layout', [
            'headContent' => $headContent,
            'headScripts' => $headScripts,
            'bodyScripts' => $bodyScripts,
            'customCss' => $customCss,
            'renderedBlocks' => $renderedBlocks,
            'site' => $site,
            'themeConfig' => $themeConfig,
            'content' => $content,
        ])->render();

        return $this->minifier->minify($html);
    }

    private function renderBlock(Block $block, Site $site): string
    {
        $sanitizedData = $this->sanitizer->sanitizeBlock($block);
        $childrenHtml = '';

        foreach ($block->children()->orderBy('order')->get() as $child) {
            $childrenHtml .= $this->renderBlock($child, $site);
        }

        $viewName = "blocks.{$block->type}";
        if (!View::exists($viewName)) {
            return "<!-- Unknown block type: {$block->type} -->";
        }

        return View::make($viewName, [
            'data' => $sanitizedData,
            'children' => $childrenHtml,
            'site' => $site,
        ])->render();
    }
}
