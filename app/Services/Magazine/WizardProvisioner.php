<?php

namespace App\Services\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Magazine\MagArticle;
use App\Models\Magazine\WizardSession;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WizardProvisioner
{
    /**
     * Provision a wizard session into a real magazine issue.
     *
     * @throws \DomainException on validation failures
     * @throws \RuntimeException on infrastructure failures
     */
    public function provision(WizardSession $session): MagazineIssue
    {
        // ─── Validate preconditions ───
        if ($session->status !== 'active') {
            throw new \DomainException("Session is {$session->status}, not active.");
        }

        if ($session->current_step !== 7) {
            throw new \DomainException("Session is on step {$session->current_step}, not step 7.");
        }

        $brief = $session->step1_brief;
        if (!$brief) {
            throw new \DomainException('Step 1 (Brief) has not been completed.');
        }

        $structure = $session->step2_structure;
        if (!$structure || empty($structure['articles'] ?? [])) {
            throw new \DomainException('Step 2 (Structure) has no articles.');
        }

        // Resolve site from tenant
        $site = Site::first(); // PHASE_12_PORT: multi-site tenants need site selection in wizard
        if (!$site) {
            throw new \DomainException('No site found for this tenant.');
        }

        // Index step4/5/6 by article_slug for lookup
        $analysesBySlug = collect($session->step4_analyses ?? [])->keyBy('article_slug');
        $directionsBySlug = collect($session->step5_directions ?? [])->keyBy('article_slug');
        $thumbnailsBySlug = collect($session->step6_thumbnails ?? [])->keyBy('article_slug');

        $articles = $structure['articles'];
        $totalPages = array_sum(array_column($articles, 'pages'));

        try {
            return DB::transaction(function () use ($session, $brief, $articles, $totalPages, $site, $analysesBySlug, $directionsBySlug, $thumbnailsBySlug) {
                // ─── a. Create magazine_issues row ───
                $title = $session->title ?? mb_substr($brief['feeling'] ?? 'Untitled', 0, 120);

                $issue = MagazineIssue::create([
                    'tenant_id' => $session->tenant_id,
                    'site_id' => $site->id,
                    'title' => $title,
                    'theme' => $brief['feeling'] ?? '',
                    'intention' => $brief['reader_state'] ?? '',
                    'target_page_count' => $totalPages,
                    'status' => 'draft',
                    'wizard_brief' => $brief,
                    'created_by' => Auth::id() ?? $session->user_id,
                ]);

                // ─── b. Create a Page (editor_mode=magazine) for the editor ───
                $page = Page::create([
                    'site_id' => $site->id,
                    'title' => $title,
                    'slug' => Str::slug($title) . '-' . substr(md5($issue->id . time()), 0, 8),
                    'status' => 'draft',
                    'editor_mode' => 'magazine',
                ]);

                $issue->update(['linked_page_id' => $page->id]);

                // ─── c. Create articles and pages ───
                $globalPageOrder = 0;

                foreach ($articles as $idx => $articleDef) {
                    $slug = $articleDef['slug'] ?? Str::slug($articleDef['title'] ?? "article-{$idx}");
                    $analysis = $analysesBySlug->get($slug);
                    $direction = $directionsBySlug->get($slug);
                    $thumbnails = $thumbnailsBySlug->get($slug);

                    // Build wizard_plan combining analysis + direction + thumbnails
                    $wizardPlan = null;
                    if ($analysis || $direction || $thumbnails) {
                        $wizardPlan = [];
                        if ($analysis) {
                            $wizardPlan['voice'] = $analysis['voice'] ?? null;
                            $wizardPlan['beats'] = $analysis['beats'] ?? [];
                            $wizardPlan['spread_assignments'] = $analysis['spread_assignments'] ?? [];
                        }
                        if ($direction) {
                            $wizardPlan['chosen_direction'] = $direction['chosen'] ?? null;
                        }
                        if ($thumbnails) {
                            $wizardPlan['thumbnails'] = $thumbnails['spreads'] ?? [];
                        }
                    }

                    $article = MagArticle::create([
                        'issue_id' => $issue->id,
                        'slug' => $slug,
                        'title' => $articleDef['title'] ?? 'Untitled',
                        'page_count' => $articleDef['pages'] ?? 2,
                        'rhythm' => $articleDef['rhythm'] ?? null,
                        'role' => $articleDef['role'] ?? null,
                        'wizard_plan' => $wizardPlan,
                        'sort_order' => $idx,
                    ]);

                    // Fetch the actual post content from CMS
                    $postBody = $this->getPostBody($site, $slug, $articleDef['title'] ?? '');
                    $postExcerpt = $this->getPostExcerpt($site, $slug, $articleDef['title'] ?? '');
                    $postImage = $this->getPostImage($site, $slug, $articleDef['title'] ?? '');

                    // Create mag_pages + starter elements for this article
                    $spreadAssignments = $analysis['spread_assignments'] ?? [];
                    $articleTitle = $articleDef['title'] ?? 'Untitled';
                    $pageCount = $articleDef['pages'] ?? 2;

                    for ($i = 0; $i < $pageCount; $i++) {
                        $assignment = $spreadAssignments[$i] ?? null;
                        $pageNum = $globalPageOrder + 1;
                        $role = $assignment['role'] ?? null;
                        $density = $assignment['density'] ?? ($articleDef['rhythm'] ?? 'medium');

                        MagPage::create([
                            'page_id' => $page->id,
                            'page_number' => $pageNum,
                            'page_size' => ['width' => 595, 'height' => 842],
                            'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
                            'bleed' => ['top' => 9, 'right' => 9, 'bottom' => 9, 'left' => 9],
                            'columns' => ['count' => 1, 'gutter' => 12],
                            'baseline_grid' => ['increment' => 14, 'start' => 36],
                            'spread_role' => $role,
                            'spread_density' => $assignment['density'] ?? null,
                            'spread_tension' => $assignment['tension'] ?? null,
                        ]);

                        // Generate starter elements based on page role
                        $this->createStarterElements($page->id, $pageNum, $articleTitle, $role, $density, $i === 0, $postBody, $postImage, $i, $pageCount);

                        $globalPageOrder++;
                    }
                }

                // ─── d. Mark session as provisioned ───
                $session->update([
                    'status' => 'provisioned',
                    'provisioned_issue_id' => $issue->id,
                ]);

                Log::channel('single')->info('Wizard provisioned', [
                    'session_id' => $session->id,
                    'issue_id' => $issue->id,
                    'page_id' => $page->id,
                    'articles' => count($articles),
                    'total_pages' => $globalPageOrder,
                ]);

                return $issue;
            });
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Wizard provisioning failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Provisioning failed. Please try again or contact support.', 0, $e);
        }
    }

    /**
     * Create starter elements on a page with actual post content.
     * Uses 6 distinct layout patterns to create editorial variety.
     */
    private function createStarterElements(
        string $pageId, int $pageNumber, string $articleTitle,
        ?string $role, string $density, bool $isFirstPage,
        string $postBody, string $postImage, int $pageIndex, int $totalPages
    ): void {
        $pw = 595; $ph = 842; $ml = 48; $mt = 60; $cw = 499;
        $contentPages = max($totalPages - 1, 1);
        $bodyChunk = $this->getBodyChunk($postBody, max(0, $pageIndex - 1), $contentPages);
        $imgStyle = ['fill' => ['color' => '#e8e4df', 'opacity' => 1, 'gradient' => null], 'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'], 'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0], 'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0];
        $textData = fn(string $c) => ['content' => $c, 'overflow' => 'hidden', 'autoSize' => 'none', 'columnsInFrame' => 1, 'columnGap' => 12, 'textInset' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0], 'verticalAlign' => 'top'];
        $titleTypo = ['fontFamily' => "'Instrument Serif', Georgia, serif", 'fontSize' => 36, 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.15, 'textAlign' => 'left', 'textColor' => '#1a1a1a', 'letterSpacing' => 0, 'textTransform' => 'none'];
        $bodyTypo = ['fontFamily' => "'Inter', system-ui, sans-serif", 'fontSize' => 11, 'fontWeight' => 400, 'fontStyle' => 'normal', 'lineHeight' => 1.75, 'textAlign' => 'left', 'textColor' => '#333', 'letterSpacing' => 0, 'textTransform' => 'none'];
        $el = fn(array $props) => MagElement::create(array_merge(['page_id' => $pageId, 'page_number' => $pageNumber, 'style' => $this->defaultStyle()], $props));

        // Choose opener variant based on article hash for deterministic variety
        $variant = crc32($articleTitle) % 4;

        if ($isFirstPage) {
            $firstChunk = $this->getBodyChunk($postBody, 0, $contentPages);

            switch ($variant) {
                case 0: // Full-bleed image + overlaid title at bottom
                    $el(['type' => 'image_frame', 'name' => 'Hero', 'x' => 0, 'y' => 0, 'width' => $pw, 'height' => $ph * 0.65, 'z_index' => 1, 'data' => ['src' => $postImage, 'alt' => $articleTitle, 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
                    $el(['type' => 'text_frame', 'name' => 'Title', 'x' => $ml, 'y' => $ph * 0.70, 'width' => $cw, 'height' => 80, 'z_index' => 2, 'data' => $textData('<p>' . e($articleTitle) . '</p>'), 'typography' => $titleTypo]);
                    if ($firstChunk) $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $ml, 'y' => $ph * 0.82, 'width' => $cw * 0.6, 'height' => $ph * 0.13, 'z_index' => 3, 'data' => $textData($firstChunk), 'typography' => array_merge($bodyTypo, ['fontSize' => 10])]);
                    break;

                case 1: // Minimal — title centered in vast whitespace, no image
                    $el(['type' => 'rectangle', 'name' => 'Rule', 'x' => $ml, 'y' => $ph * 0.38, 'width' => 50, 'height' => 1, 'z_index' => 1, 'data' => ['fillColor' => '#ccc'], 'style' => $this->defaultStyle('#ccc')]);
                    $el(['type' => 'text_frame', 'name' => 'Title', 'x' => $ml, 'y' => $ph * 0.40, 'width' => $cw * 0.7, 'height' => 120, 'z_index' => 2, 'data' => $textData('<p>' . e($articleTitle) . '</p>'), 'typography' => array_merge($titleTypo, ['fontSize' => 42, 'lineHeight' => 1.1])]);
                    break;

                case 2: // Side image + title left, body below
                    $imgW = $cw * 0.42;
                    $el(['type' => 'text_frame', 'name' => 'Title', 'x' => $ml, 'y' => $mt, 'width' => $cw * 0.52, 'height' => 90, 'z_index' => 2, 'data' => $textData('<p>' . e($articleTitle) . '</p>'), 'typography' => array_merge($titleTypo, ['fontSize' => 28])]);
                    $el(['type' => 'image_frame', 'name' => 'Side image', 'x' => $ml + $cw * 0.58, 'y' => $mt, 'width' => $imgW, 'height' => $ph * 0.5, 'z_index' => 1, 'data' => ['src' => $postImage, 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
                    if ($firstChunk) $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $ml, 'y' => $mt + 100, 'width' => $cw * 0.52, 'height' => $ph - $mt - 160, 'z_index' => 3, 'data' => $textData($firstChunk), 'typography' => $bodyTypo]);
                    break;

                case 3: // Top image strip + large title + two-column body
                    $el(['type' => 'image_frame', 'name' => 'Image strip', 'x' => $ml, 'y' => $mt, 'width' => $cw, 'height' => 200, 'z_index' => 1, 'data' => ['src' => $postImage, 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
                    $el(['type' => 'text_frame', 'name' => 'Title', 'x' => $ml, 'y' => $mt + 215, 'width' => $cw * 0.8, 'height' => 70, 'z_index' => 2, 'data' => $textData('<p>' . e($articleTitle) . '</p>'), 'typography' => array_merge($titleTypo, ['fontSize' => 26])]);
                    if ($firstChunk) $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $ml, 'y' => $mt + 300, 'width' => $cw, 'height' => $ph - $mt - 360, 'z_index' => 3, 'data' => array_merge($textData($firstChunk), ['columnsInFrame' => 2, 'columnGap' => 24]), 'typography' => array_merge($bodyTypo, ['fontSize' => 10])]);
                    break;
            }

        } elseif ($role === 'breath' || $density === 'breath') {
            // Breathing page — 3 variants
            $bv = $pageNumber % 3;
            if ($bv === 0) {
                // Thin centered rule
                $el(['type' => 'rectangle', 'name' => 'Rule', 'x' => $pw * 0.4, 'y' => $ph * 0.48, 'width' => $pw * 0.2, 'height' => 1, 'z_index' => 1, 'data' => ['fillColor' => '#e0ddd8'], 'style' => $this->defaultStyle('#e0ddd8')]);
            } elseif ($bv === 1) {
                // Full-page placeholder image
                $el(['type' => 'image_frame', 'name' => 'Visual pause', 'x' => 0, 'y' => 0, 'width' => $pw, 'height' => $ph, 'z_index' => 1, 'data' => ['src' => '', 'alt' => 'Visual pause', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
            } else {
                // Subtle textured background
                $el(['type' => 'rectangle', 'name' => 'Background', 'x' => 0, 'y' => 0, 'width' => $pw, 'height' => $ph, 'z_index' => 1, 'data' => ['fillColor' => '#f5f3ef'], 'style' => $this->defaultStyle('#f5f3ef')]);
                $el(['type' => 'rectangle', 'name' => 'Rule', 'x' => 0, 'y' => $ph * 0.48, 'width' => $pw, 'height' => 1, 'z_index' => 2, 'data' => ['fillColor' => '#e8e4df'], 'style' => $this->defaultStyle('#e8e4df')]);
            }

        } else {
            // Content pages — 4 layout variants
            $cv = ($pageNumber + crc32($articleTitle)) % 4;
            $content = $bodyChunk ?: '<p>...</p>';

            switch ($cv) {
                case 0: // Single centered column (narrow, readable)
                    $textW = min($cw, 380);
                    $textX = $ml + ($cw - $textW) / 2;
                    $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $textX, 'y' => $mt, 'width' => $textW, 'height' => $ph - $mt - 60, 'z_index' => 1, 'data' => $textData($content), 'typography' => $bodyTypo]);
                    break;

                case 1: // Two-column
                    $gap = 24; $colW = ($cw - $gap) / 2;
                    $halfContent = $this->splitHtml($content);
                    $el(['type' => 'text_frame', 'name' => 'Body L', 'x' => $ml, 'y' => $mt, 'width' => $colW, 'height' => $ph - $mt - 60, 'z_index' => 1, 'data' => $textData($halfContent[0]), 'typography' => array_merge($bodyTypo, ['fontSize' => 10])]);
                    $el(['type' => 'text_frame', 'name' => 'Body R', 'x' => $ml + $colW + $gap, 'y' => $mt, 'width' => $colW, 'height' => $ph - $mt - 60, 'z_index' => 2, 'data' => $textData($halfContent[1]), 'typography' => array_merge($bodyTypo, ['fontSize' => 10])]);
                    break;

                case 2: // Text with side image placeholder
                    $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $ml, 'y' => $mt, 'width' => $cw * 0.55, 'height' => $ph - $mt - 60, 'z_index' => 1, 'data' => $textData($content), 'typography' => $bodyTypo]);
                    $el(['type' => 'image_frame', 'name' => 'Side image', 'x' => $ml + $cw * 0.60, 'y' => $mt + 20, 'width' => $cw * 0.38, 'height' => 320, 'z_index' => 2, 'data' => ['src' => '', 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
                    break;

                case 3: // Top image + text below
                    $el(['type' => 'image_frame', 'name' => 'Top image', 'x' => $ml, 'y' => $mt, 'width' => $cw, 'height' => 240, 'z_index' => 1, 'data' => ['src' => '', 'alt' => '', 'fit' => 'cover', 'focalPoint' => ['x' => 0.5, 'y' => 0.5]], 'style' => $imgStyle]);
                    $el(['type' => 'text_frame', 'name' => 'Body', 'x' => $ml, 'y' => $mt + 255, 'width' => min($cw, 420), 'height' => $ph - $mt - 315, 'z_index' => 2, 'data' => $textData($content), 'typography' => $bodyTypo]);
                    break;
            }
        }
    }

    /**
     * Split HTML roughly in half by paragraphs.
     */
    private function splitHtml(string $html): array
    {
        preg_match_all('/<p[^>]*>.*?<\/p>/s', $html, $m);
        $paras = $m[0] ?? [$html];
        $mid = (int) ceil(count($paras) / 2);
        return [
            implode('', array_slice($paras, 0, $mid)),
            implode('', array_slice($paras, $mid)) ?: '<p>...</p>',
        ];
    }

    /**
     * Look up a post by slug or title and return its body as HTML paragraphs.
     */
    private function getPostBody(Site $site, string $slug, string $title): string
    {
        $post = $this->findPost($site, $slug, $title);
        if (!$post) return '';

        $blocks = $post->blocks()
            ->whereIn('type', ['text', 'paragraph', 'rich_text', 'heading', 'quote'])
            ->orderBy('order')
            ->get();

        $html = '';
        foreach ($blocks as $block) {
            $content = $block->data['content'] ?? '';
            if (trim(strip_tags($content))) {
                $html .= $content;
            }
        }

        return $html ?: '';
    }

    private function getPostExcerpt(Site $site, string $slug, string $title): string
    {
        $post = $this->findPost($site, $slug, $title);
        return $post?->excerpt ?? '';
    }

    private function getPostImage(Site $site, string $slug, string $title): string
    {
        $post = $this->findPost($site, $slug, $title);
        return $post?->featured_image ?? '';
    }

    private function findPost(Site $site, string $slug, string $title): ?Post
    {
        $post = $site->posts()->where('slug', $slug)->first();
        if (!$post && $title) {
            $post = $site->posts()->where('title', $title)->first();
        }
        return $post;
    }

    /**
     * Split body HTML into roughly equal chunks for pagination.
     */
    private function getBodyChunk(string $html, int $chunkIndex, int $totalChunks): string
    {
        if (!$html || $totalChunks <= 0) return '';

        // Split by paragraphs
        preg_match_all('/<p[^>]*>.*?<\/p>/s', $html, $matches);
        $paragraphs = $matches[0] ?? [];

        if (empty($paragraphs)) {
            // No <p> tags — wrap plain text
            $words = preg_split('/\s+/u', strip_tags($html), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($words)) return '';
            $perChunk = max(1, (int) ceil(count($words) / $totalChunks));
            $chunk = array_slice($words, $chunkIndex * $perChunk, $perChunk);
            return '<p>' . implode(' ', $chunk) . '</p>';
        }

        $perChunk = max(1, (int) ceil(count($paragraphs) / $totalChunks));
        $chunk = array_slice($paragraphs, $chunkIndex * $perChunk, $perChunk);

        return implode("\n", $chunk);
    }

    private function defaultStyle(?string $fillColor = null): array
    {
        return [
            'fill' => ['color' => $fillColor, 'opacity' => 1, 'gradient' => null],
            'stroke' => ['color' => 'transparent', 'width' => 0, 'style' => 'solid', 'alignment' => 'center'],
            'cornerRadius' => ['tl' => 0, 'tr' => 0, 'br' => 0, 'bl' => 0],
            'opacity' => 1, 'shadow' => null, 'innerShadow' => null, 'blendMode' => 'normal', 'blur' => 0,
        ];
    }
}
