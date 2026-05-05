<?php

namespace App\Domain\Import\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Categories\Services\CategoryService;
use App\Domain\Import\Data\ImportResult;
use App\Domain\Pages\Services\PageService;
use App\Domain\Posts\Services\PostService;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordPressImporter
{
    public function __construct(
        private GutenbergParser $gutenbergParser,
        private AttachmentImporter $attachmentImporter,
        private ContentRewriter $contentRewriter,
        private CategoryService $categoryService,
        private PageService $pageService,
        private PostService $postService,
        private BlockService $blockService,
    ) {
    }

    /**
     * Full import from a WXR XML file.
     */
    public function import(Site $site, string $xmlPath, array $options = []): ImportResult
    {
        return $this->importWithProgress($site, $xmlPath, $options, null);
    }

    /**
     * Import with step-by-step progress callback.
     */
    public function importWithProgress(Site $site, string $xmlPath, array $options, ?\Closure $onProgress): ImportResult
    {
        $result = new ImportResult();
        $importCategories = $options['import_categories'] ?? true;
        $importPages = $options['import_pages'] ?? true;
        $importPosts = $options['import_posts'] ?? true;
        $importMedia = $options['import_media'] ?? true;
        $progress = $onProgress ?? fn() => null;

        $progress('parsing', 'Parsing WordPress export file...', 5);

        $parsed = $this->parseWxr($xmlPath);
        $baseUrl = $parsed['base_url'] ?? '';

        $totalItems = count($parsed['categories'] ?? []) + count($parsed['attachments'] ?? [])
            + count($parsed['pages'] ?? []) + count($parsed['posts'] ?? []);

        $progress('parsing', "Found: " . count($parsed['categories'] ?? []) . " categories, "
            . count($parsed['pages'] ?? []) . " pages, "
            . count($parsed['posts'] ?? []) . " posts, "
            . count($parsed['attachments'] ?? []) . " attachments", 10);

        // 1. Import categories
        $categoryMap = [];
        if ($importCategories && !empty($parsed['categories'])) {
            $progress('categories', 'Importing categories...', 15);
            $categoryMap = $this->importCategories($site, $parsed['categories'], $result);
            $progress('categories', "Imported {$result->categories} categories", 20, ['categories' => $result->categories]);
        }

        // 2. Import attachments
        $attachmentMap = [];
        if ($importMedia && !empty($parsed['attachments'])) {
            $total = count($parsed['attachments']);
            $progress('media', "Downloading {$total} media files...", 25);

            // Import with per-item progress
            $imported = 0;
            foreach ($parsed['attachments'] as $attachment) {
                $wpId = (int) ($attachment['wp_id'] ?? 0);
                $url = $attachment['url'] ?? '';
                if (!$wpId || !$url) continue;

                try {
                    $singleMap = $this->attachmentImporter->importAttachments($site, [$attachment], $baseUrl);
                    $attachmentMap = array_merge($attachmentMap, $singleMap);
                } catch (\Throwable $e) {
                    $result->addWarning("Failed to download: " . basename($url));
                }

                $imported++;
                $pct = 25 + (int)(($imported / max($total, 1)) * 25); // 25-50%
                if ($imported % 3 === 0 || $imported === $total) {
                    $progress('media', "Downloaded {$imported}/{$total} media files", $pct, ['attachments' => $imported]);
                }
            }
            $result->attachments = count($attachmentMap);
            $progress('media', "Media import complete: {$result->attachments} files", 50, ['attachments' => $result->attachments]);
        }

        // 3. Import pages
        if ($importPages && !empty($parsed['pages'])) {
            $total = count($parsed['pages']);
            $progress('pages', "Importing {$total} pages...", 55);
            $this->importPages($site, $parsed['pages'], $attachmentMap, $baseUrl, $result);
            $progress('pages', "Imported {$result->pages} pages", 70, ['pages' => $result->pages]);
        }

        // 4. Import posts
        if ($importPosts && !empty($parsed['posts'])) {
            $total = count($parsed['posts']);
            $progress('posts', "Importing {$total} posts...", 75);
            $this->importPosts($site, $parsed['posts'], $categoryMap, $attachmentMap, $baseUrl, $result);
            $progress('posts', "Imported {$result->posts} posts", 95, ['posts' => $result->posts]);
        }

        return $result;
    }

    /**
     * Preview what will be imported without actually importing.
     */
    public function preview(string $xmlPath): array
    {
        $parsed = $this->parseWxr($xmlPath);
        $warnings = [];

        // Count unique block types across all content
        $unknownBlocks = [];
        $allContent = array_merge(
            array_column($parsed['pages'] ?? [], 'content'),
            array_column($parsed['posts'] ?? [], 'content'),
        );

        foreach ($allContent as $content) {
            preg_match_all('/<!-- wp:(\S+?)[\s{\/]/', $content ?? '', $matches);
            foreach ($matches[1] as $blockType) {
                $blockType = preg_replace('/^core\//', '', $blockType);
                if (!in_array($blockType, [
                    'paragraph', 'heading', 'image', 'separator', 'quote', 'pullquote',
                    'list', 'list-item', 'columns', 'column', 'group', 'cover', 'html', 'code',
                    'preformatted', 'verse', 'embed', 'table', 'buttons', 'button',
                    'gallery', 'media-text', 'details', 'file', 'video', 'audio',
                    'spacer', 'social-links', 'social-link', 'navigation', 'navigation-link', 'navigation-submenu',
                    'template-part', 'query', 'post-template', 'latest-posts',
                    'site-title', 'site-logo', 'hr',
                    'post-title', 'post-content', 'post-date', 'post-excerpt', 'post-featured-image',
                    'post-author', 'post-author-name', 'post-terms', 'post-navigation-link',
                    'post-comments-form', 'comments', 'comment-template',
                    'comment-author-name', 'comment-date', 'comment-content',
                    'comment-reply-link', 'comment-edit-link',
                    'query-pagination', 'query-pagination-next', 'query-pagination-previous',
                    'query-pagination-numbers', 'query-title', 'query-no-results',
                    'read-more', 'avatar', 'page-list', 'loginout', 'search',
                    'categories', 'tag-cloud', 'archives', 'pattern', 'block', 'freeform',
                ])) {
                    $unknownBlocks[$blockType] = ($unknownBlocks[$blockType] ?? 0) + 1;
                }
            }
        }

        if (!empty($unknownBlocks)) {
            $count = array_sum($unknownBlocks);
            $types = implode(', ', array_keys($unknownBlocks));
            $warnings[] = "{$count} unknown block types will be converted to text: {$types}";
        }

        return [
            'site_title' => $parsed['site_title'] ?? '',
            'site_description' => $parsed['site_description'] ?? '',
            'base_url' => $parsed['base_url'] ?? '',
            'categories' => count($parsed['categories'] ?? []),
            'pages' => count($parsed['pages'] ?? []),
            'posts' => count($parsed['posts'] ?? []),
            'attachments' => count($parsed['attachments'] ?? []),
            'warnings' => $warnings,
        ];
    }

    /**
     * Parse WXR XML file using XMLReader for memory efficiency.
     */
    public function parseWxr(string $xmlPath): array
    {
        $result = [
            'site_title' => '',
            'site_description' => '',
            'base_url' => '',
            'categories' => [],
            'attachments' => [],
            'pages' => [],
            'posts' => [],
        ];

        $reader = new \XMLReader();
        if (!$reader->open($xmlPath)) {
            throw new \RuntimeException("Cannot open WXR file: {$xmlPath}");
        }

        // We need SimpleXML for individual items since WXR structure is complex
        $xml = simplexml_load_file($xmlPath);
        if (!$xml) {
            throw new \RuntimeException("Invalid XML in WXR file: {$xmlPath}");
        }

        $channel = $xml->channel;
        $result['site_title'] = (string) $channel->title;
        $result['site_description'] = (string) $channel->description;
        $result['base_url'] = (string) $channel->link;

        // Register WordPress namespaces
        $namespaces = $xml->getNamespaces(true);
        $wp = $namespaces['wp'] ?? 'http://wordpress.org/export/1.2/';
        $dc = $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/';
        $content_ns = $namespaces['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $excerpt_ns = $namespaces['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

        // Parse categories from wp:category elements
        foreach ($channel->children($wp)->category ?? [] as $cat) {
            $catSlug = (string) $cat->category_nicename;
            $result['categories'][] = [
                'wp_id' => (string) $cat->term_id,
                'name' => (string) $cat->cat_name,
                'slug' => \App\Support\Slugify::slug(urldecode($catSlug)) ?: $catSlug,
                'parent_slug' => (string) $cat->category_parent,
                'description' => (string) $cat->category_description,
            ];
        }

        // Also parse wp:tag elements as categories for simplicity
        foreach ($channel->children($wp)->tag ?? [] as $tag) {
            // Skip tags, they're not categories
        }

        // Parse items
        foreach ($channel->item as $item) {
            $wpData = $item->children($wp);
            $contentData = $item->children($content_ns);
            $excerptData = $item->children($excerpt_ns);

            $postType = (string) $wpData->post_type;
            $status = (string) $wpData->status;
            $wpPostId = (int) $wpData->post_id;

            // Sanitize WP slug: decode URL-encoded chars, then convert to ASCII-safe slug
            $rawSlug = (string) $wpData->post_name;
            $cleanSlug = \App\Support\Slugify::slug(urldecode($rawSlug));

            $itemData = [
                'wp_id' => $wpPostId,
                'title' => (string) $item->title,
                'slug' => $cleanSlug ?: null,
                'content' => (string) $contentData->encoded,
                'excerpt' => (string) ($excerptData->encoded ?? ''),
                'status' => $this->mapStatus($status),
                'date' => (string) $wpData->post_date,
                'parent_id' => (int) $wpData->post_parent,
            ];

            // Extract categories/tags assigned to this item
            $itemCategories = [];
            foreach ($item->category ?? [] as $cat) {
                $attrs = $cat->attributes();
                $domain = (string) ($attrs['domain'] ?? '');
                if ($domain === 'category') {
                    $raw = (string) ($attrs['nicename'] ?? $cat);
                    $itemCategories[] = \App\Support\Slugify::slug(urldecode($raw)) ?: $raw;
                }
            }
            $itemData['categories'] = $itemCategories;

            // Extract featured image (post_meta _thumbnail_id)
            foreach ($wpData->postmeta ?? [] as $meta) {
                $metaKey = (string) $meta->meta_key;
                $metaValue = (string) $meta->meta_value;
                if ($metaKey === '_thumbnail_id') {
                    $itemData['featured_image_wp_id'] = (int) $metaValue;
                }
            }

            switch ($postType) {
                case 'attachment':
                    $result['attachments'][] = [
                        'wp_id' => $wpPostId,
                        'url' => (string) $wpData->attachment_url,
                        'title' => $itemData['title'],
                        'alt' => '', // Alt text would be in postmeta _wp_attachment_image_alt
                    ];

                    // Try to get alt text from meta
                    foreach ($wpData->postmeta ?? [] as $meta) {
                        if ((string) $meta->meta_key === '_wp_attachment_image_alt') {
                            $result['attachments'][count($result['attachments']) - 1]['alt'] = (string) $meta->meta_value;
                        }
                    }
                    break;

                case 'page':
                    $result['pages'][] = $itemData;
                    break;

                case 'post':
                    $result['posts'][] = $itemData;
                    break;

                // Skip nav_menu_item, wp_template, wp_navigation, etc.
            }
        }

        $reader->close();

        return $result;
    }

    /**
     * Import categories with parent hierarchy.
     */
    private function importCategories(Site $site, array $categories, ImportResult $result): array
    {
        $map = []; // wp_slug => cms_category_id
        $sortOrder = 0;

        // First pass: create all categories without parents
        foreach ($categories as $cat) {
            $slug = $cat['slug'] ?: $cat['name'];

            // Check if already imported (idempotency)
            $existing = Category::where('site_id', $site->id)
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $map[$slug] = $existing->id;
                $result->addSkipped('category', $cat['name']);
                continue;
            }

            $category = $this->categoryService->createCategory([
                'name' => $cat['name'],
                'slug' => $slug,
                'description' => $cat['description'] ?? '',
                'sort_order' => $sortOrder++,
            ], $site);

            $map[$slug] = $category->id;
            $result->categories++;
        }

        // Second pass: set parent relationships
        foreach ($categories as $cat) {
            if (!empty($cat['parent_slug']) && isset($map[$cat['slug']]) && isset($map[$cat['parent_slug']])) {
                Category::where('id', $map[$cat['slug']])->update([
                    'parent_id' => $map[$cat['parent_slug']],
                ]);
            }
        }

        return $map;
    }

    /**
     * Import pages with parent hierarchy.
     */
    private function importPages(Site $site, array $pages, array $attachmentMap, string $baseUrl, ImportResult $result): void
    {
        $pageMap = []; // wp_id => cms_page_id
        $sortOrder = 0;

        // Sort by parent_id to ensure parents are created first
        usort($pages, fn($a, $b) => ($a['parent_id'] ?? 0) <=> ($b['parent_id'] ?? 0));

        foreach ($pages as $pageData) {
            try {
                // Idempotency check
                $existing = Page::where('site_id', $site->id)
                    ->where('slug', $pageData['slug'])
                    ->first();

                if ($existing) {
                    $pageMap[$pageData['wp_id']] = $existing->id;
                    $result->addSkipped('page', $pageData['title']);
                    continue;
                }

                $parentId = null;
                if ($pageData['parent_id'] && isset($pageMap[$pageData['parent_id']])) {
                    $parentId = $pageMap[$pageData['parent_id']];
                }

                $page = $this->pageService->createPage([
                    'title' => $pageData['title'],
                    'slug' => $pageData['slug'] ?: null,
                    'status' => $pageData['status'],
                    'parent_id' => $parentId,
                    'sort_order' => $sortOrder++,
                    'published_at' => $pageData['status'] === 'published' ? ($pageData['date'] ?: now()) : null,
                ], $site);

                $pageMap[$pageData['wp_id']] = $page->id;

                // Parse and sync blocks
                if (!empty($pageData['content'])) {
                    $blocks = $this->gutenbergParser->parse($pageData['content']);
                    $blocks = $this->contentRewriter->rewrite($blocks, $attachmentMap, $baseUrl);
                    if (!empty($blocks)) {
                        $this->blockService->syncBlocks($page, $blocks);
                    }
                }

                $result->pages++;
            } catch (\Throwable $e) {
                $result->addError("Failed to import page '{$pageData['title']}': {$e->getMessage()}");
                Log::warning("WordPress import: Failed to import page", [
                    'title' => $pageData['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Import posts with category assignments and featured images.
     */
    private function importPosts(
        Site $site,
        array $posts,
        array $categoryMap,
        array $attachmentMap,
        string $baseUrl,
        ImportResult $result,
    ): void {
        foreach ($posts as $postData) {
            try {
                // Idempotency check
                $existing = Post::where('site_id', $site->id)
                    ->where('slug', $postData['slug'])
                    ->first();

                if ($existing) {
                    $result->addSkipped('post', $postData['title']);
                    continue;
                }

                // Resolve category
                $categoryId = null;
                foreach ($postData['categories'] ?? [] as $catSlug) {
                    if (isset($categoryMap[$catSlug])) {
                        $categoryId = $categoryMap[$catSlug];
                        break;
                    }
                }

                // Resolve featured image
                $featuredImage = null;
                $featuredWpId = $postData['featured_image_wp_id'] ?? null;
                if ($featuredWpId && isset($attachmentMap[$featuredWpId])) {
                    $featuredImage = $attachmentMap[$featuredWpId];
                }

                $post = $this->postService->createPost([
                    'title' => $postData['title'],
                    'slug' => $postData['slug'] ?: null,
                    'excerpt' => $postData['excerpt'] ?? null,
                    'status' => $postData['status'],
                    'category_id' => $categoryId,
                    'featured_image' => $featuredImage,
                    'published_at' => $postData['status'] === 'published' ? ($postData['date'] ?: now()) : null,
                ], $site);

                // Parse and sync blocks
                if (!empty($postData['content'])) {
                    $blocks = $this->gutenbergParser->parse($postData['content']);
                    $blocks = $this->contentRewriter->rewrite($blocks, $attachmentMap, $baseUrl);
                    if (!empty($blocks)) {
                        $this->blockService->syncBlocks($post, $blocks);
                    }
                }

                $result->posts++;
            } catch (\Throwable $e) {
                $result->addError("Failed to import post '{$postData['title']}': {$e->getMessage()}");
                Log::warning("WordPress import: Failed to import post", [
                    'title' => $postData['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Map WordPress post status to CMS status.
     */
    private function mapStatus(string $wpStatus): string
    {
        return match ($wpStatus) {
            'publish' => 'published',
            'draft' => 'draft',
            'pending' => 'draft',
            'private' => 'draft',
            'future' => 'draft',
            'trash' => 'archived',
            default => 'draft',
        };
    }
}
