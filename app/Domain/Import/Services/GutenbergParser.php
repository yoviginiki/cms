<?php

namespace App\Domain\Import\Services;

use Illuminate\Support\Str;

class GutenbergParser
{
    private const SKIP_BLOCKS = [
        'spacer', 'social-links', 'social-link', 'navigation', 'template-part',
        'query', 'post-template', 'latest-posts', 'site-title', 'site-logo',
        'post-title', 'post-content', 'post-date', 'post-excerpt', 'post-featured-image',
        'loginout', 'search', 'categories', 'tag-cloud', 'archives',
        'comments', 'comment-template', 'comment-author-name', 'comment-date',
        'comment-content', 'comment-reply-link', 'comment-edit-link',
        'post-comments-form', 'post-navigation-link', 'post-terms',
        'query-pagination', 'query-pagination-next', 'query-pagination-previous',
        'query-pagination-numbers', 'query-title', 'query-no-results',
        'read-more', 'avatar', 'post-author', 'post-author-name',
        'page-list', 'navigation-link', 'navigation-submenu',
        'pattern', 'block', 'freeform',
    ];

    /**
     * Parse Gutenberg block content into CMS block tree.
     */
    public function parse(string $content): array
    {
        $content = trim($content);
        if (empty($content)) {
            return [];
        }

        $blocks = $this->extractBlocks($content);

        return $this->mapBlocks($blocks);
    }

    /**
     * Extract raw block structures from Gutenberg HTML.
     */
    private function extractBlocks(string $content): array
    {
        $blocks = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            // Try to find next block comment
            $nextBlock = strpos($content, '<!-- wp:', $offset);

            if ($nextBlock === false) {
                // Remaining content — check if there's meaningful HTML
                $remaining = trim(substr($content, $offset));
                if ($remaining && strip_tags($remaining) !== '') {
                    $blocks[] = [
                        'type' => 'paragraph',
                        'attrs' => [],
                        'innerHTML' => $remaining,
                        'innerBlocks' => [],
                    ];
                }
                break;
            }

            // Capture any loose HTML before the block as a paragraph
            if ($nextBlock > $offset) {
                $loose = trim(substr($content, $offset, $nextBlock - $offset));
                if ($loose && strip_tags($loose) !== '') {
                    $blocks[] = [
                        'type' => 'paragraph',
                        'attrs' => [],
                        'innerHTML' => $loose,
                        'innerBlocks' => [],
                    ];
                }
            }

            // Check for self-closing block: <!-- wp:blocktype /-->  or  <!-- wp:blocktype {"attrs"} /-->
            if (preg_match('/<!-- wp:(\S+?)(\s+(\{.*?\}))?\s*\/-->/s', $content, $m, 0, $nextBlock)) {
                if (strpos($content, $m[0]) === $nextBlock) {
                    $blocks[] = [
                        'type' => $m[1],
                        'attrs' => isset($m[3]) ? (json_decode($m[3], true) ?? []) : [],
                        'innerHTML' => '',
                        'innerBlocks' => [],
                    ];
                    $offset = $nextBlock + strlen($m[0]);
                    continue;
                }
            }

            // Opening block: <!-- wp:blocktype --> or <!-- wp:blocktype {"attrs"} -->
            if (!preg_match('/<!-- wp:(\S+?)(\s+(\{.*?\}))?\s*-->/s', $content, $m, 0, $nextBlock)) {
                $offset = $nextBlock + 1;
                continue;
            }

            $blockType = $m[1];
            $attrs = isset($m[3]) ? (json_decode($m[3], true) ?? []) : [];
            $openTagEnd = $nextBlock + strlen($m[0]);

            // Find matching closing tag, handling nesting
            $closePos = $this->findClosingTag($content, $blockType, $openTagEnd);
            if ($closePos === false) {
                $offset = $openTagEnd;
                continue;
            }

            $closingTag = "<!-- /wp:{$blockType} -->";
            $innerHTML = substr($content, $openTagEnd, $closePos - $openTagEnd);

            // Recursively extract inner blocks
            $innerBlocks = $this->extractBlocks($innerHTML);
            // If we found inner blocks, strip them from innerHTML to get pure content
            $pureHTML = $innerHTML;
            if (!empty($innerBlocks)) {
                $pureHTML = $this->stripBlockComments($innerHTML);
            }

            $blocks[] = [
                'type' => $blockType,
                'attrs' => $attrs,
                'innerHTML' => trim($pureHTML),
                'innerBlocks' => $innerBlocks,
            ];

            $offset = $closePos + strlen($closingTag);
        }

        return $blocks;
    }

    /**
     * Find the matching closing tag position, handling nested blocks of the same type.
     */
    private function findClosingTag(string $content, string $blockType, int $startPos): int|false
    {
        $depth = 1;
        $pos = $startPos;

        while ($depth > 0) {
            $nextOpen = strpos($content, "<!-- wp:{$blockType}", $pos);
            $nextClose = strpos($content, "<!-- /wp:{$blockType} -->", $pos);

            if ($nextClose === false) {
                return false;
            }

            // Check if the next open is a self-closing block
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                // Check if self-closing
                $afterOpen = substr($content, $nextOpen, 200);
                if (preg_match('/^<!-- wp:' . preg_quote($blockType, '/') . '(\s+\{.*?\})?\s*\/-->/s', $afterOpen)) {
                    // Self-closing, skip it
                    $pos = $nextOpen + 1;
                    continue;
                }
                // It's a real opening tag — check it has the right format
                if (preg_match('/^<!-- wp:' . preg_quote($blockType, '/') . '(\s+\{.*?\})?\s*-->/s', $afterOpen)) {
                    $depth++;
                }
                $pos = $nextOpen + 1;
            } else {
                $depth--;
                if ($depth === 0) {
                    return $nextClose;
                }
                $pos = $nextClose + 1;
            }
        }

        return false;
    }

    /**
     * Strip Gutenberg block comments from HTML, keeping the content.
     */
    private function stripBlockComments(string $html): string
    {
        // Remove opening and closing block comments
        $html = preg_replace('/<!-- \/?wp:\S+?(\s+\{.*?\})?\s*\/?-->/', '', $html);

        return trim($html);
    }

    /**
     * Map extracted Gutenberg blocks to CMS block format.
     */
    private function mapBlocks(array $rawBlocks): array
    {
        $cmsBlocks = [];
        $order = 0;

        foreach ($rawBlocks as $raw) {
            $mapped = $this->mapBlock($raw, $order);
            if ($mapped !== null) {
                $cmsBlocks[] = $mapped;
                $order++;
            }
        }

        return $cmsBlocks;
    }

    /**
     * Map a single Gutenberg block to a CMS block.
     */
    private function mapBlock(array $raw, int $order): ?array
    {
        // Strip wp: namespace prefix if present
        $wpType = preg_replace('/^core\//', '', $raw['type']);

        // Skip blocks we don't import
        if (in_array($wpType, self::SKIP_BLOCKS)) {
            return null;
        }

        $result = match ($wpType) {
            'paragraph' => $this->mapParagraph($raw),
            'heading' => $this->mapHeading($raw),
            'image' => $this->mapImage($raw),
            'separator', 'hr' => $this->mapDivider(),
            'quote', 'pullquote' => $this->mapQuote($raw),
            'list' => $this->mapList($raw),
            'columns' => $this->mapColumns($raw),
            'column' => $this->mapColumn($raw),
            'group' => $this->mapGroup($raw),
            'cover' => $this->mapCover($raw),
            'html', 'code', 'preformatted', 'verse' => $this->mapCodeBlock($raw),
            'embed' => $this->mapEmbed($raw),
            'table' => $this->mapTable($raw),
            'buttons', 'button' => $this->mapButton($raw),
            'gallery' => $this->mapGallery($raw),
            'media-text' => $this->mapMediaText($raw),
            'list-item' => null, // list-item is a child of list, handled by mapList
            'details' => $this->mapDetails($raw),
            'file' => $this->mapFile($raw),
            'video' => $this->mapVideo($raw),
            'audio' => $this->mapAudio($raw),
            'spacer' => null, // skip spacers
            default => $this->mapUnknown($raw, $wpType),
        };

        if ($result === null) {
            return null;
        }

        $result['order'] = $order;
        $result['id'] = Str::uuid()->toString();

        return $result;
    }

    private function mapParagraph(array $raw): ?array
    {
        $content = $this->extractInnerContent($raw['innerHTML']);
        if (empty(trim(strip_tags($content)))) {
            return null;
        }

        return [
            'type' => 'text',
            'data' => ['content' => $content],
            'children' => [],
        ];
    }

    private function mapHeading(array $raw): array
    {
        $level = $raw['attrs']['level'] ?? null;

        // Try to detect from HTML tag if not in attrs
        if (!$level && preg_match('/<h([1-6])/', $raw['innerHTML'], $m)) {
            $level = (int) $m[1];
        }

        $level = $level ? "h{$level}" : 'h2';
        $text = strip_tags($raw['innerHTML']);

        return [
            'type' => 'heading',
            'data' => ['text' => trim($text), 'level' => $level],
            'children' => [],
        ];
    }

    private function mapImage(array $raw): array
    {
        $src = '';
        $alt = '';
        $caption = '';

        // Extract from HTML
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $src = $m[1];
        }
        if (preg_match('/<img[^>]+alt=["\']([^"\']*)["\']/', $raw['innerHTML'], $m)) {
            $alt = $m[1];
        }
        if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $raw['innerHTML'], $m)) {
            $caption = strip_tags($m[1]);
        }

        // Override with attrs if available
        $src = $raw['attrs']['url'] ?? $src;
        $alt = $raw['attrs']['alt'] ?? $alt;

        return [
            'type' => 'image',
            'data' => [
                'url' => $src,
                'alt' => $alt,
                'caption' => $caption,
                'wp_attachment_id' => $raw['attrs']['id'] ?? null,
            ],
            'children' => [],
        ];
    }

    private function mapDivider(): array
    {
        return [
            'type' => 'divider',
            'data' => [],
            'children' => [],
        ];
    }

    private function mapQuote(array $raw): array
    {
        $content = '';
        $citation = '';

        if (preg_match('/<blockquote[^>]*>(.*?)<\/blockquote>/is', $raw['innerHTML'], $m)) {
            $inner = $m[1];
            // Extract citation
            if (preg_match('/<cite[^>]*>(.*?)<\/cite>/is', $inner, $cm)) {
                $citation = strip_tags($cm[1]);
                $inner = str_replace($cm[0], '', $inner);
            }
            $content = trim($inner);
        } else {
            $content = $raw['innerHTML'];
        }

        return [
            'type' => 'quote',
            'data' => [
                'content' => $content,
                'citation' => $citation,
            ],
            'children' => [],
        ];
    }

    private function mapList(array $raw): array
    {
        $html = $raw['innerHTML'];
        // Keep the full list HTML as content for the text block
        if (!preg_match('/<[ou]l/i', $html)) {
            $ordered = ($raw['attrs']['ordered'] ?? false) ? 'ol' : 'ul';
            $html = "<{$ordered}>{$html}</{$ordered}>";
        }

        return [
            'type' => 'text',
            'data' => ['content' => $html],
            'children' => [],
        ];
    }

    private function mapColumns(array $raw): array
    {
        $children = [];
        $order = 0;

        foreach ($raw['innerBlocks'] as $innerBlock) {
            if ($innerBlock['type'] === 'column') {
                $columnChildren = $this->mapBlocks($innerBlock['innerBlocks']);
                foreach ($columnChildren as &$child) {
                    $child['order'] = $order++;
                }
                $children = array_merge($children, $columnChildren);
            }
        }

        $columnCount = count($raw['innerBlocks']);
        if ($columnCount < 2) $columnCount = 2;
        if ($columnCount > 6) $columnCount = 6;

        return [
            'type' => 'columns',
            'data' => ['column_count' => $columnCount, 'gap' => 'medium'],
            'children' => $children,
        ];
    }

    private function mapColumn(array $raw): ?array
    {
        // Columns are handled by mapColumns — individual columns
        // become children of the columns block
        // If encountered standalone, wrap as a section
        $children = $this->mapBlocks($raw['innerBlocks']);

        if (empty($children)) {
            return null;
        }

        return [
            'type' => 'text',
            'data' => ['content' => $this->stripBlockComments($raw['innerHTML'])],
            'children' => [],
        ];
    }

    private function mapGroup(array $raw): ?array
    {
        // Groups are containers — flatten their children
        $children = $this->mapBlocks($raw['innerBlocks']);

        if (empty($children)) {
            // If no inner blocks, try the HTML content
            $content = $this->extractInnerContent($raw['innerHTML']);
            if (empty(trim(strip_tags($content)))) {
                return null;
            }
            return [
                'type' => 'text',
                'data' => ['content' => $content],
                'children' => [],
            ];
        }

        // If group has multiple children, return as columns with 1 column
        // or just return the first child if there's only one
        if (count($children) === 1) {
            return $children[0];
        }

        // Return children as separate blocks — we'll flatten in the caller
        // For now, wrap in a text block with combined content
        return [
            'type' => 'columns',
            'data' => ['column_count' => 2, 'gap' => 'medium'],
            'children' => $children,
        ];
    }

    private function mapCover(array $raw): array
    {
        $title = '';
        $backgroundImage = '';

        // Background image from attrs or style
        if (isset($raw['attrs']['url'])) {
            $backgroundImage = $raw['attrs']['url'];
        } elseif (preg_match('/background-image:\s*url\(([^)]+)\)/i', $raw['innerHTML'], $m)) {
            $backgroundImage = trim($m[1], "'\" ");
        } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $backgroundImage = $m[1];
        }

        // Title from inner heading
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $raw['innerHTML'], $m)) {
            $title = strip_tags($m[1]);
        }

        // Try inner blocks for heading
        if (empty($title)) {
            foreach ($raw['innerBlocks'] as $inner) {
                if ($inner['type'] === 'heading' || $inner['type'] === 'core/heading') {
                    $title = strip_tags($inner['innerHTML']);
                    break;
                }
            }
        }

        return [
            'type' => 'hero',
            'data' => [
                'title' => $title ?: 'Untitled',
                'background_image' => $backgroundImage,
            ],
            'children' => [],
        ];
    }

    private function mapCodeBlock(array $raw): array
    {
        $content = $raw['innerHTML'];
        // Wrap in pre/code if not already
        if (!str_contains($content, '<pre') && !str_contains($content, '<code')) {
            $content = '<pre><code>' . htmlspecialchars(strip_tags($content)) . '</code></pre>';
        }

        return [
            'type' => 'text',
            'data' => ['content' => $content],
            'children' => [],
        ];
    }

    private function mapEmbed(array $raw): array
    {
        $url = $raw['attrs']['url'] ?? '';
        $content = "<p>Embedded content: <a href=\"{$url}\">{$url}</a></p>";

        return [
            'type' => 'text',
            'data' => ['content' => $content],
            'children' => [],
        ];
    }

    private function mapTable(array $raw): array
    {
        return [
            'type' => 'text',
            'data' => ['content' => $raw['innerHTML']],
            'children' => [],
        ];
    }

    private function mapButton(array $raw): ?array
    {
        // Extract button link and text
        if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $raw['innerHTML'], $m)) {
            return [
                'type' => 'text',
                'data' => ['content' => "<p><a href=\"{$m[1]}\">{$m[2]}</a></p>"],
                'children' => [],
            ];
        }
        return null;
    }

    private function mapGallery(array $raw): ?array
    {
        // Map gallery to multiple image blocks wrapped in columns
        $images = [];
        foreach ($raw['innerBlocks'] as $inner) {
            if ($inner['type'] === 'image' || $inner['type'] === 'core/image') {
                $images[] = $this->mapImage($inner);
            }
        }

        if (empty($images)) {
            // Try parsing images from HTML
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $matches);
            foreach ($matches[1] as $src) {
                $images[] = [
                    'type' => 'image',
                    'data' => ['url' => $src, 'alt' => '', 'caption' => ''],
                    'children' => [],
                ];
            }
        }

        if (empty($images)) {
            return null;
        }

        // If single image, return as-is
        if (count($images) === 1) {
            return $images[0];
        }

        // Multiple images — wrap in columns
        $colCount = min(count($images), 4);
        $order = 0;
        foreach ($images as &$img) {
            $img['order'] = $order++;
            $img['id'] = Str::uuid()->toString();
        }

        return [
            'type' => 'columns',
            'data' => ['column_count' => $colCount, 'gap' => 'small'],
            'children' => $images,
        ];
    }

    private function mapMediaText(array $raw): ?array
    {
        // media-text is a two-column layout: image + text side by side
        $children = [];
        $order = 0;

        // Extract image
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $alt = '';
            if (preg_match('/<img[^>]+alt=["\']([^"\']*)["\']/', $raw['innerHTML'], $am)) {
                $alt = $am[1];
            }
            $children[] = [
                'type' => 'image',
                'data' => ['url' => $m[1], 'alt' => $alt, 'caption' => ''],
                'children' => [],
                'order' => $order++,
                'id' => Str::uuid()->toString(),
            ];
        }

        // Extract text from inner blocks or HTML
        if (!empty($raw['innerBlocks'])) {
            $innerMapped = $this->mapBlocks($raw['innerBlocks']);
            foreach ($innerMapped as &$ib) {
                $ib['order'] = $order++;
            }
            $children = array_merge($children, $innerMapped);
        } else {
            // Try to get text content
            $textContent = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $raw['innerHTML']);
            $textContent = $this->extractInnerContent(trim($textContent));
            if (!empty(trim(strip_tags($textContent)))) {
                $children[] = [
                    'type' => 'text',
                    'data' => ['content' => $textContent],
                    'children' => [],
                    'order' => $order++,
                    'id' => Str::uuid()->toString(),
                ];
            }
        }

        if (empty($children)) return null;

        return [
            'type' => 'columns',
            'data' => ['column_count' => 2, 'gap' => 'medium'],
            'children' => $children,
        ];
    }

    private function mapDetails(array $raw): array
    {
        // details/summary → accordion with single item
        $title = '';
        $content = '';

        if (preg_match('/<summary[^>]*>(.*?)<\/summary>/is', $raw['innerHTML'], $m)) {
            $title = strip_tags($m[1]);
        }

        // Content is everything after summary
        $content = preg_replace('/<summary[^>]*>.*?<\/summary>/is', '', $raw['innerHTML']);
        $content = $this->extractInnerContent(trim($content));

        return [
            'type' => 'accordion',
            'data' => ['items' => [['title' => $title ?: 'Details', 'content' => $content]]],
            'children' => [],
        ];
    }

    private function mapFile(array $raw): array
    {
        $url = '';
        $label = '';

        if (preg_match('/<a[^>]+href=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $url = $m[1];
        }
        if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $raw['innerHTML'], $m)) {
            $label = strip_tags($m[1]);
        }

        return [
            'type' => 'button',
            'data' => ['text' => $label ?: 'Download File', 'url' => $url, 'style' => 'outline', 'size' => 'md', 'target' => '_blank'],
            'children' => [],
        ];
    }

    private function mapVideo(array $raw): array
    {
        $url = '';
        if (preg_match('/<video[^>]+src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $url = $m[1];
        } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $url = $m[1];
        }
        $url = $raw['attrs']['src'] ?? $url;

        return [
            'type' => 'video',
            'data' => ['url' => $url, 'autoplay' => false, 'muted' => false],
            'children' => [],
        ];
    }

    private function mapAudio(array $raw): array
    {
        $url = '';
        if (preg_match('/src=["\']([^"\']+)["\']/i', $raw['innerHTML'], $m)) {
            $url = $m[1];
        }

        return [
            'type' => 'text',
            'data' => ['content' => '<p><a href="' . e($url) . '">Listen to audio</a></p>'],
            'children' => [],
        ];
    }

    private function mapUnknown(array $raw, string $wpType): ?array
    {
        // Try to extract meaningful content from unknown blocks
        $content = $this->extractInnerContent($raw['innerHTML']);
        if (empty(trim(strip_tags($content)))) {
            return null;
        }

        return [
            'type' => 'text',
            'data' => ['content' => $content],
            'children' => [],
        ];
    }

    /**
     * Clean up inner HTML content, removing WP-specific classes.
     */
    private function extractInnerContent(string $html): string
    {
        // Strip WP-specific classes
        $html = preg_replace('/\s*class="[^"]*wp-block[^"]*"/', '', $html);
        $html = preg_replace('/\s*class="[^"]*has-[^"]*"/', '', $html);

        // Remove empty class attributes
        $html = preg_replace('/\s*class="\s*"/', '', $html);

        return trim($html);
    }
}
