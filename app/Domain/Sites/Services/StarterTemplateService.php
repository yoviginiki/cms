<?php

namespace App\Domain\Sites\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Domain\Posts\Services\PostService;
use App\Domain\Sites\Services\AiSiteContentService;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Sprint 3 — Applies starter templates to new sites.
 * Creates pages with pre-built block content.
 */
class StarterTemplateService
{
    public function __construct(
        private PageService $pageService,
        private BlockService $blockService,
        private PostService $postService,
        private AiSiteContentService $aiContent,
    ) {}

    /**
     * Get all available starter templates.
     */
    public function getTemplates(): array
    {
        return [
            [
                'id' => 'blank',
                'name' => 'Blank Site',
                'description' => 'Start from scratch with an empty homepage.',
                'pages' => ['home'],
            ],
            [
                'id' => 'blog',
                'name' => 'Blog',
                'description' => 'Blog-ready with posts, categories, and archive pages.',
                'pages' => ['home', 'about', 'contact', 'blog'],
            ],
            [
                'id' => 'portfolio',
                'name' => 'Portfolio',
                'description' => 'Showcase your work with gallery and project pages.',
                'pages' => ['home', 'about', 'work', 'contact'],
            ],
            [
                'id' => 'business',
                'name' => 'Business',
                'description' => 'Professional business site with services and team.',
                'pages' => ['home', 'about', 'services', 'team', 'contact'],
            ],
            [
                'id' => 'full',
                'name' => 'Full Site',
                'description' => 'A complete site: home, landing, catalog, portfolio, contact, blog, about, and features.',
                'pages' => ['home', 'landing', 'catalog', 'portfolio', 'contact', 'blog', 'about', 'features'],
            ],
        ];
    }

    /**
     * Apply a starter template to a site.
     * Creates pages with default blocks. Idempotent — skips existing slugs.
     */
    public function apply(Site $site, string $templateId, ?string $topic = null): array
    {
        $template = collect($this->getTemplates())->firstWhere('id', $templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Unknown template: ' . $templateId, 'pages_created' => 0];
        }

        // "Full Site" + a named business type → AI-tailored, industry-specific copy
        // (null → generic placeholder content).
        $content = ($templateId === 'full' && $topic) ? $this->aiContent->generate($topic) : null;

        $created = 0;
        $skipped = 0;
        $pageDefinitions = $this->getPageDefinitions($templateId, $content);

        foreach ($pageDefinitions as $def) {
            // Skip if page with this slug already exists
            $existing = $site->pages()->where('slug', $def['slug'])->withTrashed()->first();
            if ($existing) {
                $skipped++;
                continue;
            }

            $page = $this->pageService->createPage([
                'title' => $def['title'],
                'slug' => $def['slug'],
                'status' => 'published',
            ], $site);

            // Create blocks for this page
            if (!empty($def['blocks'])) {
                $this->blockService->syncBlocks($page, $def['blocks']);
            }

            $created++;
        }

        // Set homepage if site doesn't have one
        $settings = $site->settings ?? [];
        if (empty($settings['homepage_id'])) {
            $homePage = $site->pages()->where('slug', 'home')->first();
            if ($homePage) {
                $site->update(['settings' => array_merge($settings, [
                    'homepage_id' => $homePage->id,
                    'homepage_type' => 'page',
                ])]);
            }
        }

        // Create sample posts for templates that have a blog/posts page
        $postsCreated = 0;
        if (in_array($templateId, ['blog', 'portfolio', 'full'])) {
            $postsCreated = $this->createSamplePosts($site, $templateId, $content);
        }

        $msg = "Created {$created} pages";
        if ($postsCreated > 0) $msg .= " and {$postsCreated} sample posts";
        if ($skipped > 0) $msg .= ", skipped {$skipped} existing";

        return [
            'success' => true,
            'message' => $msg,
            'pages_created' => $created,
            'pages_skipped' => $skipped,
            'posts_created' => $postsCreated,
        ];
    }

    /**
     * Page definitions with blocks for each template.
     */
    private function getPageDefinitions(string $templateId, ?array $content = null): array
    {
        if ($templateId === 'full') {
            $c = $content ?? [];
            // Industry images only when a topic produced AI content.
            $img = $content ? ($c['_images'] ?? 'business,office') : '';
            return [
                $this->homePage(
                    $c['home']['heading'] ?? 'Welcome',
                    $c['home']['subtext'] ?? 'Everything you need to launch — already laid out.',
                    $c['home'] ?? [], $img,
                ),
                $this->landingPage($c['landing'] ?? [], $img),
                $this->catalogPage($c['catalog'] ?? [], $img),
                $this->portfolioPage($c['portfolio'] ?? [], $img),
                $this->contactPage($c['contact'] ?? []),
                $this->blogPage($c['blog'] ?? []),
                $this->aboutPage($c['about'] ?? [], $img),
                $this->featuresPage($c['features'] ?? []),
            ];
        }

        return match ($templateId) {
            'blank' => [$this->homePage('Welcome', 'Your new website is ready. Start building!')],
            'blog' => [
                $this->homePage('Welcome to My Blog', 'Thoughts, stories, and ideas.'),
                $this->aboutPage(),
                $this->contactPage(),
                $this->blogPage(),
            ],
            'portfolio' => [
                $this->homePage('Creative Portfolio', 'Showcasing work that matters.'),
                $this->aboutPage(),
                $this->workPage(),
                $this->contactPage(),
            ],
            'business' => [
                $this->homePage('Growing Your Business', 'Professional solutions for modern challenges.'),
                $this->aboutPage(),
                $this->servicesPage(),
                $this->teamPage(),
                $this->contactPage(),
            ],
            default => [$this->homePage('Welcome', 'Start building your site.')],
        };
    }

    // ─── Page builders ───

    private function homePage(string $heading, string $subtext, array $c = [], string $img = ''): array
    {
        $intro = $this->column([
            $this->block('heading', ['text' => $c['heading'] ?? $heading, 'level' => 'h1', 'fontSize' => '2.5rem']),
            $this->block('paragraph', ['content' => '<p>' . ($c['subtext'] ?? $subtext) . '</p>']),
            $this->block('button', ['text' => $c['cta'] ?? 'Get Started', 'url' => '/contact', 'style' => 'primary', 'size' => 'lg']),
        ]);

        return [
            'title' => 'Home', 'slug' => 'home',
            'blocks' => [
                $img
                    ? $this->section([
                        $this->row('1/2+1/2', [
                            $intro,
                            $this->column([$this->imageBlock($img, 1, $heading)]),
                        ]),
                    ], ['padding_top' => '70px', 'padding_bottom' => '70px', 'max_width' => '1100px'])
                    : $this->section([$this->row('1', [$intro])], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '800px']),
            ],
        ];
    }

    private function aboutPage(array $c = [], string $img = ''): array
    {
        $text = $this->column([
            $this->block('heading', ['text' => $c['heading'] ?? 'About Us', 'level' => 'h1', 'fontSize' => '2rem']),
            $this->block('paragraph', ['content' => '<p>' . ($c['paragraph1'] ?? 'Tell your story here. What makes you unique? What drives your work?') . '</p>']),
            $this->block('paragraph', ['content' => '<p>' . ($c['paragraph2'] ?? 'Share your mission, values, and the journey that brought you here.') . '</p>']),
        ]);

        return [
            'title' => 'About', 'slug' => 'about',
            'blocks' => [
                $img
                    ? $this->section([
                        $this->row('1/2+1/2', [$text, $this->column([$this->imageBlock($img, 3, 'About us')])]),
                    ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px'])
                    : $this->section([$this->row('1', [$text])], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '800px']),
            ],
        ];
    }

    private function contactPage(array $c = []): array
    {
        return [
            'title' => 'Contact', 'slug' => 'contact',
            'blocks' => [
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => $c['heading'] ?? 'Get in Touch', 'level' => 'h1', 'fontSize' => '2rem']),
                            $this->block('paragraph', ['content' => '<p>' . ($c['intro'] ?? 'We\'d love to hear from you. Reach out and we\'ll get back to you soon.') . '</p><p><strong>Email:</strong> hello@example.com</p>']),
                        ]),
                        $this->column([
                            $this->block('contact-form', [
                                'recipient_email' => '',
                                'submit_label' => 'Send Message',
                                'success_message' => 'Thank you! We\'ll be in touch.',
                                'fields' => [
                                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                                    ['label' => 'Email', 'type' => 'email', 'required' => true],
                                    ['label' => 'Message', 'type' => 'textarea', 'required' => true],
                                ],
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1000px']),
            ],
        ];
    }

    private function blogPage(array $c = []): array
    {
        return [
            'title' => 'Blog', 'slug' => 'blog',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => $c['heading'] ?? 'Blog', 'level' => 'h1', 'fontSize' => '2rem']),
                            $this->block('latestposts', ['limit' => 9, 'columns' => 3, 'layout' => 'cards', 'showExcerpt' => true, 'showImage' => true, 'showDate' => true]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1200px']),
            ],
        ];
    }

    private function workPage(): array
    {
        return [
            'title' => 'Work', 'slug' => 'work',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Our Work', 'level' => 'h1', 'fontSize' => '2rem']),
                            $this->block('paragraph', ['content' => '<p>A selection of our recent projects and creative work.</p>']),
                            $this->block('gallery', ['layout' => 'grid', 'columns' => 3, 'gap' => '16px', 'images' => []]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1200px']),
            ],
        ];
    }

    private function servicesPage(): array
    {
        return [
            'title' => 'Services', 'slug' => 'services',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Our Services', 'level' => 'h1', 'fontSize' => '2rem']),
                            $this->block('paragraph', ['content' => '<p>We offer professional solutions tailored to your needs.</p>']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([$this->block('heading', ['text' => 'Consulting', 'level' => 'h3']), $this->block('paragraph', ['content' => '<p>Expert guidance for your projects.</p>'])]),
                        $this->column([$this->block('heading', ['text' => 'Design', 'level' => 'h3']), $this->block('paragraph', ['content' => '<p>Beautiful, functional design.</p>'])]),
                        $this->column([$this->block('heading', ['text' => 'Development', 'level' => 'h3']), $this->block('paragraph', ['content' => '<p>Robust technical solutions.</p>'])]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),
            ],
        ];
    }

    private function teamPage(): array
    {
        return [
            'title' => 'Team', 'slug' => 'team',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Our Team', 'level' => 'h1', 'fontSize' => '2rem']),
                            $this->block('paragraph', ['content' => '<p>Meet the people behind the work.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),
            ],
        ];
    }

    private function landingPage(array $c = [], string $img = ''): array
    {
        $features = $c['features'] ?? [
            ['title' => 'Fast', 'desc' => 'Loads instantly, everywhere.'],
            ['title' => 'Simple', 'desc' => 'No clutter — just what you need.'],
            ['title' => 'Reliable', 'desc' => 'Built to last and easy to maintain.'],
        ];

        return [
            'title' => 'Landing', 'slug' => 'landing',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [$this->column([
                        $this->block('heading', ['text' => $c['heading'] ?? 'Everything you need, in one place', 'level' => 'h1', 'fontSize' => '2.75rem', 'textAlign' => 'center']),
                        $this->block('paragraph', ['content' => '<p>' . ($c['subtext'] ?? 'A clear, compelling pitch for your product or service — tell visitors why they should care, in one or two sentences.') . '</p>']),
                        $this->block('button', ['text' => $c['cta'] ?? 'Start Free', 'url' => '/contact', 'style' => 'primary', 'size' => 'lg']),
                    ])]),
                ], ['padding_top' => '90px', 'padding_bottom' => '50px', 'max_width' => '820px']),
                // Feature highlights
                $this->section([
                    $this->row('1', [$this->column([$this->block('feature-grid', ['columns' => 3, 'style' => 'icon-top', 'items' => $this->featureItems($features)])])]),
                ], ['padding_top' => '20px', 'padding_bottom' => '50px', 'max_width' => '1100px']),
                // Closing CTA
                $this->section([
                    $this->row('1', [$this->column([
                        $this->block('heading', ['text' => $c['closing_heading'] ?? 'Ready to get started?', 'level' => 'h2', 'fontSize' => '1.8rem', 'textAlign' => 'center']),
                        $this->block('button', ['text' => 'Get in Touch', 'url' => '/contact', 'style' => 'primary', 'size' => 'lg']),
                    ])]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '800px']),
            ],
        ];
    }

    private function catalogPage(array $c = [], string $img = ''): array
    {
        $src = $c['items'] ?? [
            ['title' => 'Product One', 'subtitle' => 'From $—', 'desc' => 'A short description of this product or service. Replace with your own copy.'],
            ['title' => 'Product Two', 'subtitle' => 'From $—', 'desc' => 'A short description of this product or service. Replace with your own copy.'],
            ['title' => 'Product Three', 'subtitle' => 'From $—', 'desc' => 'A short description of this product or service. Replace with your own copy.'],
        ];
        $items = [];
        foreach (array_values($src) as $i => $it) {
            $items[] = [
                'title' => (string) ($it['title'] ?? 'Item'),
                'subtitle' => (string) ($it['subtitle'] ?? ''),
                'content' => '<p>' . (string) ($it['desc'] ?? '') . '</p>',
                'images' => $img ? [$this->aiContent->imageUrl($img, 20 + $i)] : [],
            ];
        }

        return [
            'title' => 'Catalog', 'slug' => 'catalog',
            'blocks' => [
                $this->section([
                    $this->row('1', [$this->column([
                        $this->block('heading', ['text' => $c['heading'] ?? 'Catalog', 'level' => 'h1', 'fontSize' => '2rem']),
                        $this->block('paragraph', ['content' => '<p>' . ($c['intro'] ?? 'Browse our products and offerings.') . '</p>']),
                        $this->block('catalog', ['openFirst' => true, 'items' => $items]),
                    ])]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1000px']),
            ],
        ];
    }

    private function portfolioPage(array $c = [], string $img = ''): array
    {
        $images = [];
        if ($img) {
            for ($i = 0; $i < 6; $i++) {
                $images[] = $this->aiContent->imageUrl($img, 10 + $i);
            }
        }

        return [
            'title' => 'Portfolio', 'slug' => 'portfolio',
            'blocks' => [
                $this->section([
                    $this->row('1', [$this->column([
                        $this->block('heading', ['text' => $c['heading'] ?? 'Portfolio', 'level' => 'h1', 'fontSize' => '2rem']),
                        $this->block('paragraph', ['content' => '<p>' . ($c['intro'] ?? 'A selection of our recent projects and creative work.') . '</p>']),
                        $this->block('gallery', ['layout' => 'grid', 'columns' => 3, 'gap' => '16px', 'images' => $images]),
                    ])]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1200px']),
            ],
        ];
    }

    private function featuresPage(array $c = []): array
    {
        $features = $c['items'] ?? [
            ['title' => 'Powerful editor', 'desc' => 'Build pages visually with structured blocks.'],
            ['title' => 'Fast publishing', 'desc' => 'Generate static pages ready for any host.'],
            ['title' => 'Fully portable', 'desc' => 'Export your content and design anytime.'],
            ['title' => 'Themeable', 'desc' => 'Re-colour everything from design tokens.'],
            ['title' => 'Responsive', 'desc' => 'Looks right on every screen size.'],
            ['title' => 'SEO-ready', 'desc' => 'Clean markup and metadata out of the box.'],
        ];

        return [
            'title' => 'Features', 'slug' => 'features',
            'blocks' => [
                $this->section([
                    $this->row('1', [$this->column([
                        $this->block('heading', ['text' => $c['heading'] ?? 'Features', 'level' => 'h1', 'fontSize' => '2rem']),
                        $this->block('paragraph', ['content' => '<p>' . ($c['intro'] ?? 'Everything the product does, at a glance.') . '</p>']),
                    ])]),
                    $this->row('1', [$this->column([$this->block('feature-grid', ['columns' => 3, 'style' => 'icon-top', 'items' => $this->featureItems($features)])])]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),
            ],
        ];
    }

    /** Normalize AI/generic feature rows to the feature-grid item shape. */
    private function featureItems(array $rows): array
    {
        return array_map(fn ($r) => [
            'title' => (string) ($r['title'] ?? ''),
            'description' => (string) ($r['desc'] ?? $r['description'] ?? ''),
        ], array_values($rows));
    }

    private function imageBlock(string $keywords, int $lock, string $alt): array
    {
        return $this->block('image', [
            'url' => $this->aiContent->imageUrl($keywords, $lock),
            'alt' => $alt, 'size' => 'large',
        ]);
    }

    // ─── Sample posts ───

    /**
     * Create sample posts so blog/latestposts blocks have content to display.
     * Idempotent — skips if posts already exist for the site.
     */
    private function createSamplePosts(Site $site, string $templateId, ?array $content = null): int
    {
        // Skip if site already has posts
        if ($site->posts()->exists()) {
            return 0;
        }

        $posts = $this->getSamplePostDefinitions($templateId, $content);
        $created = 0;

        foreach ($posts as $def) {
            $this->postService->createPost([
                'title' => $def['title'],
                'slug' => $def['slug'],
                'excerpt' => $def['excerpt'],
                'status' => 'published',
                'published_at' => now()->subDays($created),
            ], $site);
            $created++;
        }

        return $created;
    }

    private function getSamplePostDefinitions(string $templateId, ?array $content = null): array
    {
        // Industry-specific posts from the AI, when available.
        $aiPosts = $content['blog']['posts'] ?? null;
        if (is_array($aiPosts) && $aiPosts !== []) {
            return array_map(fn ($p) => [
                'title' => (string) ($p['title'] ?? 'Post'),
                'slug' => Str::slug((string) ($p['title'] ?? 'post')) ?: 'post-' . Str::lower(Str::random(4)),
                'excerpt' => (string) ($p['excerpt'] ?? ''),
            ], array_slice(array_values($aiPosts), 0, 3));
        }

        if (in_array($templateId, ['blog', 'full'], true)) {
            return [
                [
                    'title' => 'Welcome to Our Blog',
                    'slug' => 'welcome-to-our-blog',
                    'excerpt' => 'This is your first blog post. Edit or replace it with your own content. Share your thoughts, stories, and ideas with the world.',
                ],
                [
                    'title' => '5 Tips for Getting Started',
                    'slug' => '5-tips-for-getting-started',
                    'excerpt' => 'Starting something new can be daunting. Here are five practical tips to help you hit the ground running and make the most of your journey.',
                ],
                [
                    'title' => 'The Art of Storytelling',
                    'slug' => 'the-art-of-storytelling',
                    'excerpt' => 'Great content starts with a great story. Learn how to craft compelling narratives that engage your audience and keep them coming back for more.',
                ],
            ];
        }

        // Portfolio
        return [
            [
                'title' => 'Brand Identity for Sunrise Co.',
                'slug' => 'brand-identity-sunrise',
                'excerpt' => 'A complete brand identity project including logo design, color palette, typography, and brand guidelines for a sustainable energy startup.',
            ],
            [
                'title' => 'E-Commerce Redesign',
                'slug' => 'ecommerce-redesign',
                'excerpt' => 'Redesigning the shopping experience for a fashion retailer, focusing on mobile-first design and streamlined checkout flow.',
            ],
            [
                'title' => 'Annual Report Design',
                'slug' => 'annual-report-design',
                'excerpt' => 'Editorial design for a non-profit organization\'s annual report, combining data visualization with compelling photography.',
            ],
        ];
    }

    // ─── Block helpers ───

    private function section(array $children, array $data = []): array
    {
        return [
            'id' => Str::uuid()->toString(), 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => array_merge(['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'], $data),
            'children' => $children,
        ];
    }

    private function row(string $layout, array $children): array
    {
        static $order = 0;
        return [
            'id' => Str::uuid()->toString(), 'type' => 'row', 'level' => 'row', 'order' => $order++,
            'data' => ['layout' => $layout, 'gap' => '24px'],
            'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        static $order = 0;
        return [
            'id' => Str::uuid()->toString(), 'type' => 'column', 'level' => 'column', 'order' => $order++,
            'data' => [],
            'children' => $children,
        ];
    }

    private function block(string $type, array $data): array
    {
        static $order = 0;
        return [
            'id' => Str::uuid()->toString(), 'type' => $type, 'level' => 'module', 'order' => $order++,
            'data' => $data,
            'children' => [],
        ];
    }
}
