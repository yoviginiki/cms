<?php

namespace App\Services\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Direct magazine renderer.
 * Skips the abstract LayoutEngine — creates varied pages directly
 * from content items with real post text and images.
 */
class DirectRenderer
{
    private const PW = 595;
    private const PH = 842;
    private const ML = 48;
    private const MT = 60;
    private const CW = 499;

    public function materialize(MagazineIssue $issue): Page
    {
        $issue->load('contentItems');
        $site = $issue->site;

        // Archive previous handoff
        if ($issue->linked_page_id) {
            $old = Page::find($issue->linked_page_id);
            if ($old) $old->update(['status' => 'archived', 'slug' => $old->slug . '-archived-' . time()]);
        }

        $page = Page::create([
            'site_id' => $site->id,
            'title' => $issue->title,
            'slug' => Str::slug($issue->title) . '-' . substr(md5($issue->id . time()), 0, 8),
            'status' => 'draft',
            'editor_mode' => 'magazine',
        ]);

        // Collect posts from content items
        $articles = [];
        foreach ($issue->contentItems as $item) {
            if ($item->source_type !== 'post' || !$item->source_id) continue;
            $post = Post::find($item->source_id);
            if (!$post) continue;

            $body = $this->getPostBody($post);
            $words = count(preg_split('/[\s]+/u', strip_tags($body), -1, PREG_SPLIT_NO_EMPTY));

            $articles[] = [
                'post' => $post,
                'title' => $post->title,
                'body' => $body,
                'words' => $words,
                'image' => $post->featured_image ?: '',
                'pullquote' => $this->extractPullquote($body),
            ];
        }

        if (empty($articles)) {
            throw new \RuntimeException('No articles with content found');
        }

        // Build pages
        $pageNum = 0;
        $layouts = ['cover', 'toc']; // first two pages are structural

        // Page 1: Cover
        $pageNum++;
        $this->createMagPage($page->id, $pageNum);
        $this->coverPage($page->id, $pageNum, $issue->title, $issue->theme ?? '', $articles[0]['image']);

        // Page 2: TOC
        $pageNum++;
        $this->createMagPage($page->id, $pageNum);
        $this->tocPage($page->id, $pageNum, $articles);

        // Article pages
        $openerVariant = 0;
        $contentVariant = 0;
        foreach ($articles as $idx => $article) {
            $pagesForArticle = $this->pagesNeeded($article['words']);

            for ($i = 0; $i < $pagesForArticle; $i++) {
                $pageNum++;
                $this->createMagPage($page->id, $pageNum);

                if ($i === 0) {
                    // Article opener — 4 rotating variants
                    $this->articleOpener($page->id, $pageNum, $article, $openerVariant % 4);
                    $openerVariant++;
                } else {
                    // Content page — 4 rotating variants
                    $chunk = $this->getBodyChunk($article['body'], $i - 1, max($pagesForArticle - 1, 1));
                    $this->contentPage($page->id, $pageNum, $chunk, $article, $contentVariant % 4);
                    $contentVariant++;
                }
            }

            // Insert a breath page between articles (not after last)
            if ($idx < count($articles) - 1 && $idx % 2 === 1) {
                $pageNum++;
                $this->createMagPage($page->id, $pageNum);
                $this->breathPage($page->id, $pageNum, $pageNum % 3);
            }
        }

        // Closing page
        $pageNum++;
        $this->createMagPage($page->id, $pageNum);
        $this->closingPage($page->id, $pageNum, $issue->title);

        // Update issue
        $issue->update([
            'linked_page_id' => $page->id,
            'status' => 'handed_off',
            'target_page_count' => $pageNum,
        ]);

        return $page;
    }

    private function pagesNeeded(int $words): int
    {
        if ($words > 800) return 4;
        if ($words > 400) return 3;
        if ($words > 150) return 2;
        return 1;
    }

    private function createMagPage(string $pageId, int $num): void
    {
        MagPage::create([
            'page_id' => $pageId,
            'page_number' => $num,
            'page_size' => ['width' => self::PW, 'height' => self::PH],
            'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
            'bleed' => ['top' => 9, 'right' => 9, 'bottom' => 9, 'left' => 9],
            'columns' => ['count' => 1, 'gutter' => 12],
            'baseline_grid' => ['increment' => 14, 'start' => 36],
        ]);
    }

    // ═══════════════════════════════════════
    // COVER
    // ═══════════════════════════════════════

    private function coverPage(string $pid, int $pn, string $title, string $subtitle, string $image): void
    {
        $this->el($pid, $pn, 'image_frame', 0, 0, self::PW, self::PH, 1,
            ['src' => $image, 'alt' => $title, 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
            null, $this->imgStyle());

        // Dark gradient overlay
        $this->el($pid, $pn, 'rectangle', 0, self::PH * 0.55, self::PW, self::PH * 0.45, 2,
            ['fillColor' => 'rgba(0,0,0,0.5)'], null, $this->fillStyle('rgba(0,0,0,0.5)'));

        $this->el($pid, $pn, 'text_frame', self::ML + 10, self::PH * 0.62, self::CW - 20, 100, 3,
            $this->td('<p>' . e($title) . '</p>'),
            ['fontFamily' => "'Instrument Serif', Georgia, serif", 'fontSize' => 48, 'fontWeight' => 400, 'lineHeight' => 1.1, 'textAlign' => 'left', 'textColor' => '#ffffff', 'fontStyle' => 'normal', 'letterSpacing' => 0, 'textTransform' => 'none']);

        if ($subtitle) {
            $this->el($pid, $pn, 'text_frame', self::ML + 10, self::PH * 0.78, self::CW - 20, 25, 4,
                $this->td('<p>' . e($subtitle) . '</p>'),
                ['fontFamily' => "'Inter', sans-serif", 'fontSize' => 12, 'textColor' => 'rgba(255,255,255,0.6)', 'letterSpacing' => 0.1, 'textTransform' => 'uppercase', 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.5, 'textAlign' => 'left']);
        }
    }

    // ═══════════════════════════════════════
    // TABLE OF CONTENTS
    // ═══════════════════════════════════════

    private function tocPage(string $pid, int $pn, array $articles): void
    {
        $this->el($pid, $pn, 'text_frame', self::ML, self::MT, 100, 20, 1,
            $this->td('<p>Contents</p>'),
            ['fontFamily' => "'Inter', sans-serif", 'fontSize' => 8, 'textColor' => '#aaa', 'letterSpacing' => 0.2, 'textTransform' => 'uppercase', 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.5, 'textAlign' => 'left']);

        $this->el($pid, $pn, 'rectangle', self::ML, self::MT + 24, 30, 1, 2,
            ['fillColor' => '#ddd'], null, $this->fillStyle('#ddd'));

        $tocHtml = '';
        $pg = 3;
        foreach ($articles as $a) {
            $tocHtml .= '<p>' . str_pad((string) $pg, 2, ' ', STR_PAD_LEFT) . '  ' . e($a['title']) . '</p>';
            $pg += $this->pagesNeeded($a['words']) + 1;
        }

        $this->el($pid, $pn, 'text_frame', self::ML, self::MT + 35, self::CW * 0.5, self::PH - self::MT - 100, 3,
            $this->td($tocHtml),
            ['fontFamily' => "'Inter', sans-serif", 'fontSize' => 11, 'lineHeight' => 2.2, 'textColor' => '#333', 'fontWeight' => 400, 'fontStyle' => 'normal', 'textAlign' => 'left', 'letterSpacing' => 0, 'textTransform' => 'none']);
    }

    // ═══════════════════════════════════════
    // ARTICLE OPENERS — 4 variants
    // ═══════════════════════════════════════

    private function articleOpener(string $pid, int $pn, array $article, int $variant): void
    {
        $title = $article['title'];
        $image = $article['image'];
        $body = $article['body'];
        $firstChunk = $this->getBodyChunk($body, 0, max($this->pagesNeeded($article['words']) - 1, 1));

        switch ($variant) {
            case 0: // Full-bleed image + title at bottom
                $this->el($pid, $pn, 'image_frame', 0, 0, self::PW, self::PH * 0.60, 1,
                    ['src' => $image, 'alt' => $title, 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                    null, $this->imgStyle());
                $this->el($pid, $pn, 'text_frame', self::ML, self::PH * 0.65, self::CW, 80, 2,
                    $this->td('<p>' . e($title) . '</p>'), $this->titleTypo(40));
                if ($firstChunk) {
                    $this->el($pid, $pn, 'text_frame', self::ML, self::PH * 0.78, self::CW * 0.65, self::PH * 0.17, 3,
                        $this->td($firstChunk), $this->bodyTypo(10));
                }
                break;

            case 1: // Minimal typographic — title centered, lots of whitespace
                $this->el($pid, $pn, 'rectangle', self::ML, self::PH * 0.37, 50, 1, 1,
                    ['fillColor' => '#ccc'], null, $this->fillStyle('#ccc'));
                $this->el($pid, $pn, 'text_frame', self::ML, self::PH * 0.39, self::CW * 0.75, 120, 2,
                    $this->td('<p>' . e($title) . '</p>'), $this->titleTypo(42));
                break;

            case 2: // Side image + text
                $imgW = self::CW * 0.42;
                $this->el($pid, $pn, 'text_frame', self::ML, self::MT, self::CW * 0.52, 90, 2,
                    $this->td('<p>' . e($title) . '</p>'), $this->titleTypo(28));
                $this->el($pid, $pn, 'image_frame', self::ML + self::CW * 0.58, self::MT, $imgW, self::PH * 0.5, 1,
                    ['src' => $image, 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                    null, $this->imgStyle());
                if ($firstChunk) {
                    $this->el($pid, $pn, 'text_frame', self::ML, self::MT + 100, self::CW * 0.52, self::PH - self::MT - 160, 3,
                        $this->td($firstChunk), $this->bodyTypo());
                }
                break;

            case 3: // Top image strip + two-column body
                $this->el($pid, $pn, 'image_frame', self::ML, self::MT, self::CW, 200, 1,
                    ['src' => $image, 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                    null, $this->imgStyle());
                $this->el($pid, $pn, 'text_frame', self::ML, self::MT + 215, self::CW * 0.8, 70, 2,
                    $this->td('<p>' . e($title) . '</p>'), $this->titleTypo(26));
                if ($firstChunk) {
                    $this->el($pid, $pn, 'text_frame', self::ML, self::MT + 300, self::CW, self::PH - self::MT - 360, 3,
                        array_merge($this->td($firstChunk), ['columnsInFrame' => 2, 'columnGap' => 24]),
                        $this->bodyTypo(10));
                }
                break;
        }
    }

    // ═══════════════════════════════════════
    // CONTENT PAGES — 4 variants
    // ═══════════════════════════════════════

    private function contentPage(string $pid, int $pn, string $chunk, array $article, int $variant): void
    {
        $content = $chunk ?: '<p>...</p>';

        switch ($variant) {
            case 0: // Single centered narrow column
                $w = min(self::CW, 380);
                $x = self::ML + (self::CW - $w) / 2;
                $this->el($pid, $pn, 'text_frame', $x, self::MT, $w, self::PH - self::MT - 60, 1,
                    $this->td($content), $this->bodyTypo());
                break;

            case 1: // Two columns
                $gap = 24;
                $colW = (self::CW - $gap) / 2;
                $halves = $this->splitHtml($content);
                $this->el($pid, $pn, 'text_frame', self::ML, self::MT, $colW, self::PH - self::MT - 60, 1,
                    $this->td($halves[0]), $this->bodyTypo(10));
                $this->el($pid, $pn, 'text_frame', self::ML + $colW + $gap, self::MT, $colW, self::PH - self::MT - 60, 2,
                    $this->td($halves[1]), $this->bodyTypo(10));
                break;

            case 2: // Text with side image placeholder
                $this->el($pid, $pn, 'text_frame', self::ML, self::MT, self::CW * 0.55, self::PH - self::MT - 60, 1,
                    $this->td($content), $this->bodyTypo());
                $this->el($pid, $pn, 'image_frame', self::ML + self::CW * 0.60, self::MT + 20, self::CW * 0.38, 320, 2,
                    ['src' => '', 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                    null, $this->imgStyle());
                break;

            case 3: // Top image + text below
                $this->el($pid, $pn, 'image_frame', self::ML, self::MT, self::CW, 240, 1,
                    ['src' => '', 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                    null, $this->imgStyle());
                $this->el($pid, $pn, 'text_frame', self::ML, self::MT + 255, min(self::CW, 420), self::PH - self::MT - 315, 2,
                    $this->td($content), $this->bodyTypo());
                break;
        }
    }

    // ═══════════════════════════════════════
    // BREATH + CLOSING
    // ═══════════════════════════════════════

    private function breathPage(string $pid, int $pn, int $variant): void
    {
        match ($variant % 3) {
            0 => $this->el($pid, $pn, 'rectangle', self::PW * 0.4, self::PH * 0.48, self::PW * 0.2, 1, 1,
                ['fillColor' => '#e0ddd8'], null, $this->fillStyle('#e0ddd8')),
            1 => $this->el($pid, $pn, 'image_frame', 0, 0, self::PW, self::PH, 1,
                ['src' => '', 'alt' => 'Visual pause', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]],
                null, $this->imgStyle()),
            2 => (function () use ($pid, $pn) {
                $this->el($pid, $pn, 'rectangle', 0, 0, self::PW, self::PH, 1,
                    ['fillColor' => '#f5f3ef'], null, $this->fillStyle('#f5f3ef'));
                $this->el($pid, $pn, 'rectangle', 0, self::PH * 0.48, self::PW, 1, 2,
                    ['fillColor' => '#e8e4df'], null, $this->fillStyle('#e8e4df'));
            })(),
        };
    }

    private function closingPage(string $pid, int $pn, string $title): void
    {
        $this->el($pid, $pn, 'rectangle', self::PW * 0.44, self::PH * 0.45, self::PW * 0.12, 1, 1,
            ['fillColor' => '#ddd'], null, $this->fillStyle('#ddd'));
        $this->el($pid, $pn, 'text_frame', self::ML, self::PH * 0.48, self::CW, 30, 2,
            $this->td('<p>' . e($title) . '</p>'),
            ['fontFamily' => "'Inter', sans-serif", 'fontSize' => 12, 'textAlign' => 'center', 'textColor' => '#aaa', 'letterSpacing' => 0.08, 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.5, 'textTransform' => 'none']);
    }

    // ═══════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════

    private function el(string $pid, int $pn, string $type, float $x, float $y, float $w, float $h, int $z, array $data, ?array $typo = null, ?array $style = null): void
    {
        MagElement::create([
            'page_id' => $pid, 'page_number' => $pn,
            'type' => $type, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
            'z_index' => $z, 'data' => $data,
            'typography' => $typo, 'style' => $style ?? $this->defaultStyle(),
            'created_by' => Auth::id(),
        ]);
    }

    private function td(string $html): array
    {
        return ['content' => $html, 'overflow' => 'hidden', 'autoSize' => 'none', 'columnsInFrame' => 1, 'columnGap' => 12, 'textInset' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0], 'verticalAlign' => 'top'];
    }

    private function titleTypo(int $size = 36): array
    {
        return ['fontFamily' => "'Instrument Serif', Georgia, serif", 'fontSize' => $size, 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.15, 'textAlign' => 'left', 'textColor' => '#1a1a1a', 'letterSpacing' => 0, 'textTransform' => 'none'];
    }

    private function bodyTypo(int $size = 11): array
    {
        return ['fontFamily' => "'Inter', system-ui, sans-serif", 'fontSize' => $size, 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.75, 'textAlign' => 'left', 'textColor' => '#333', 'letterSpacing' => 0, 'textTransform' => 'none'];
    }

    private function imgStyle(): array
    {
        return ['fill' => ['color' => '#e8e4df', 'opacity' => 1, 'gradient' => null], 'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'], 'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0], 'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0];
    }

    private function fillStyle(string $color): array
    {
        return ['fill' => ['color' => $color, 'opacity' => 1, 'gradient' => null], 'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'], 'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0], 'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0];
    }

    private function defaultStyle(): array
    {
        return ['fill' => ['color' => null, 'opacity' => 1, 'gradient' => null], 'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'], 'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0], 'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0];
    }

    private function getPostBody(Post $post): string
    {
        $blocks = $post->blocks()->whereIn('type', ['text', 'paragraph', 'rich_text'])->orderBy('order')->get();
        $html = '';
        foreach ($blocks as $b) $html .= ($b->data['content'] ?? '');
        return $html;
    }

    private function extractPullquote(string $body): string
    {
        $text = strip_tags($body);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, 8);
        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 30 && mb_strlen($s) < 180) return $s;
        }
        return '';
    }

    private function getBodyChunk(string $html, int $idx, int $total): string
    {
        preg_match_all('/<p[^>]*>.*?<\/p>/s', $html, $m);
        $paras = $m[0] ?? [];
        if (empty($paras)) return $html ? '<p>' . mb_substr(strip_tags($html), 0, 2000) . '</p>' : '';
        $per = max(1, (int) ceil(count($paras) / $total));
        return implode("\n", array_slice($paras, $idx * $per, $per));
    }

    private function splitHtml(string $html): array
    {
        preg_match_all('/<p[^>]*>.*?<\/p>/s', $html, $m);
        $p = $m[0] ?? [$html];
        $mid = (int) ceil(count($p) / 2);
        return [implode('', array_slice($p, 0, $mid)), implode('', array_slice($p, $mid)) ?: '<p>...</p>'];
    }
}
