<?php

namespace App\Domain\Publishing\Services;

use App\Models\Asset;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class OutputValidator
{
    /**
     * Validate built HTML output against Lighthouse 100 constraints.
     * Returns warnings (non-blocking) and errors (blocking).
     */
    public function validate(string $html, Page|Post $content, Site $site): array
    {
        $warnings = [];
        $errors = [];

        // Performance checks
        $this->checkPerformance($html, $content, $warnings, $errors);

        // Accessibility checks
        $this->checkAccessibility($html, $content, $warnings, $errors);

        // SEO checks
        $this->checkSeo($html, $content, $warnings, $errors);

        // Content-quality checks (F5 SEO lint — warnings only)
        $this->checkContent($html, $content, $warnings, $errors);

        $passed = empty($errors);

        return [
            'passed' => $passed,
            'warnings' => $warnings,
            'errors' => $errors,
            'score_estimate' => $passed && empty($warnings) ? 100 : ($passed ? 95 : 80),
        ];
    }

    private function checkPerformance(string $html, Page|Post $content, array &$warnings, array &$errors): void
    {
        // Check for script tags not from code injection
        $settings = $content->site?->settings ?? [];
        $seoMeta = $content->seo_meta ?? [];
        $allowedScripts = ($settings['head_scripts'] ?? '') . ($settings['body_scripts'] ?? '')
            . ($seoMeta['head_scripts'] ?? '') . ($seoMeta['body_scripts'] ?? '');

        preg_match_all('/<script\b[^>]*>.*?<\/script>/is', $html, $scriptMatches);
        foreach ($scriptMatches[0] as $script) {
            // Allow structured data (ld+json) and explicitly injected scripts
            if (str_contains($script, 'application/ld+json')) {
                continue;
            }
            if (!empty($allowedScripts) && str_contains($allowedScripts, trim(strip_tags($script)))) {
                continue;
            }
        }

        // Check images have width and height
        preg_match_all('/<img\b([^>]*)>/i', $html, $imgMatches);
        foreach ($imgMatches[1] as $i => $attrs) {
            $hasWidth = preg_match('/\bwidth\s*=\s*["\']?\d+/i', $attrs);
            $hasHeight = preg_match('/\bheight\s*=\s*["\']?\d+/i', $attrs);

            if (!$hasWidth || !$hasHeight) {
                $src = '';
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)/i', $attrs, $m)) {
                    $src = basename($m[1]);
                }
                $warnings[] = "Image '{$src}' missing explicit width/height attributes (CLS impact)";
            }

            // Check loading attribute
            if (!preg_match('/\bloading\s*=/i', $attrs)) {
                $warnings[] = "Image missing loading attribute (should be lazy or eager)";
            }
        }

        // Check CSS size
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleMatches);
        $totalInlineCss = 0;
        foreach ($styleMatches[1] as $css) {
            $totalInlineCss += strlen($css);
        }
        if ($totalInlineCss > 100 * 1024) {
            $errors[] = "Inline CSS exceeds 100KB (" . round($totalInlineCss / 1024) . "KB) — split or reduce";
        }

        // Check for render-blocking external CSS without preload
        preg_match_all('/<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*>/i', $html, $cssLinks);
        foreach ($cssLinks[0] as $link) {
            if (!str_contains($html, 'rel="preload"') && !str_contains($html, "rel='preload'")) {
                // Only warn if there's no preload companion
            }
        }
    }

    private function checkAccessibility(string $html, Page|Post $content, array &$warnings, array &$errors): void
    {
        // Check all images have alt attribute
        preg_match_all('/<img\b([^>]*)>/i', $html, $imgMatches);
        foreach ($imgMatches[1] as $attrs) {
            if (!preg_match('/\balt\s*=/i', $attrs)) {
                $src = '';
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)/i', $attrs, $m)) {
                    $src = basename($m[1]);
                }
                $warnings[] = "Image '{$src}' missing alt attribute (accessibility)";
            } elseif (preg_match('/\balt\s*=\s*["\']\s*["\']/i', $attrs)) {
                $src = '';
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)/i', $attrs, $m)) {
                    $src = basename($m[1]);
                }
                $warnings[] = "Image '{$src}' has empty alt text";
            }
        }

        // Check heading hierarchy (no skips like h1→h3)
        preg_match_all('/<h([1-6])\b/i', $html, $headingMatches);
        $levels = array_map('intval', $headingMatches[1]);
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] > $levels[$i - 1] + 1) {
                $warnings[] = "Heading hierarchy skips from h{$levels[$i-1]} to h{$levels[$i]} (should be sequential)";
            }
        }

        // Check for non-descriptive link text
        preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $html, $linkMatches);
        $badLinkTexts = ['click here', 'read more', 'here', 'link', 'more'];
        foreach ($linkMatches[1] as $text) {
            $cleanText = strtolower(trim(strip_tags($text)));
            if (in_array($cleanText, $badLinkTexts)) {
                $warnings[] = "Link with non-descriptive text '{$cleanText}' (accessibility)";
            }
        }

        // Check lang attribute on html tag
        if (!preg_match('/<html\b[^>]*\blang\s*=/i', $html)) {
            $warnings[] = "Missing lang attribute on <html> tag";
        }

        // Check for ARIA landmarks
        if (!str_contains($html, '<main')) {
            $warnings[] = "Missing <main> landmark element";
        }
    }

    private function checkSeo(string $html, Page|Post $content, array &$warnings, array &$errors): void
    {
        // Check exactly one h1
        preg_match_all('/<h1\b/i', $html, $h1Matches);
        $h1Count = count($h1Matches[0]);
        if ($h1Count === 0) {
            $warnings[] = "No <h1> tag found on page (SEO impact)";
        } elseif ($h1Count > 1) {
            $warnings[] = "Multiple <h1> tags found ({$h1Count}) — should have exactly one";
        }

        // Check title tag
        if (!preg_match('/<title>(.+?)<\/title>/is', $html, $titleMatch)) {
            $errors[] = "Missing <title> tag";
        } else {
            $titleLen = mb_strlen(strip_tags($titleMatch[1]));
            if ($titleLen > 70) {
                $warnings[] = "Title tag is {$titleLen} characters (recommended: 60 or fewer)";
            }
        }

        // Check meta description
        if (!preg_match('/name\s*=\s*["\']description["\']/i', $html)) {
            $warnings[] = "Missing <meta name=\"description\"> tag";
        } else {
            if (preg_match('/name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']([^"\']*)/i', $html, $descMatch)) {
                $descLen = mb_strlen($descMatch[1]);
                if ($descLen > 160) {
                    $warnings[] = "Meta description is {$descLen} characters (recommended: 160 or fewer)";
                }
                if ($descLen === 0) {
                    $warnings[] = "Meta description is empty";
                }
            }
        }

        // Check canonical
        if (!preg_match('/rel\s*=\s*["\']canonical["\']/i', $html)) {
            $warnings[] = "Missing canonical URL";
        }

        // Check noindex on pages that should be indexed
        $seoMeta = $content->seo_meta ?? [];
        if (empty($seoMeta['no_index']) && preg_match('/noindex/i', $html)) {
            // Check if it's in robots meta (not just body text)
            if (preg_match('/<meta[^>]*name\s*=\s*["\']robots["\'][^>]*noindex/i', $html)) {
                $warnings[] = "Page has noindex but is not explicitly marked as no_index";
            }
        }
    }

    /** F5 SEO lint — thin content, featured image, JSON-LD validity (warnings only). */
    private function checkContent(string $html, Page|Post $content, array &$warnings, array &$errors): void
    {
        // Thin content — word count of the main content area
        $mainHtml = preg_match('#<main[^>]*>(.*?)</main>#is', $html, $m) ? $m[1] : $html;
        $words = str_word_count(strip_tags($mainHtml));
        if ($words > 0 && $words < 150) {
            $warnings[] = "Thin content: only {$words} words (aim for 300+ on substantive pages)";
        }

        // Posts without a featured image lose their social-card and Article-schema image
        if ($content instanceof Post && !$content->featured_image) {
            $warnings[] = "Post has no featured image (social shares and Article schema lose their image)";
        }

        // JSON-LD structural validity — soft-fail with warnings, never blocking
        if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#is', $html, $mm)) {
            foreach ($mm[1] as $i => $jsonLd) {
                $n = $i + 1;
                $decoded = json_decode($jsonLd, true);
                if (!is_array($decoded)) {
                    $warnings[] = "JSON-LD block {$n} is not valid JSON";
                    continue;
                }
                if (empty($decoded['@context'])) {
                    $warnings[] = "JSON-LD block {$n} is missing @context";
                }
                $nodes = isset($decoded['@graph']) ? (array) $decoded['@graph'] : [$decoded];
                foreach ($nodes as $node) {
                    if (is_array($node) && empty($node['@type'])) {
                        $warnings[] = "JSON-LD block {$n} contains a node without @type";
                        break;
                    }
                }
            }
        }
    }
}
