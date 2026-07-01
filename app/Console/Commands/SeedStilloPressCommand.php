<?php

namespace App\Console\Commands;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedStilloPressCommand extends Command
{
    protected $signature = 'stillopress:seed';

    protected $description = 'Create the 7 pages for stillopress.com with pre-built block content';

    private const TENANT_ID = '019dfba5-a96b-719d-954d-60a4a549f949';
    private const SITE_ID = '019f1d72-4f89-73e9-984e-707c32b12fb1';

    private int $rowOrder = 0;
    private int $colOrder = 0;
    private int $blockOrder = 0;

    public function handle(PageService $pageService, BlockService $blockService): int
    {
        DB::statement("SET app.current_tenant_id = '" . self::TENANT_ID . "'");

        $site = Site::find(self::SITE_ID);
        if (!$site) {
            $this->error('Site ' . self::SITE_ID . ' not found.');
            return 1;
        }

        $this->info("Seeding pages for site: {$site->name}");

        $pages = [
            $this->homePage(),
            $this->featuresPage(),
            $this->aboutPage(),
            $this->demosPage(),
            $this->pricingPage(),
            $this->docsPage(),
            $this->contactPage(),
        ];

        $created = 0;
        $skipped = 0;
        $homePageModel = null;

        foreach ($pages as $def) {
            $existing = $site->pages()->where('slug', $def['slug'])->withTrashed()->first();
            if ($existing) {
                $this->line("  ⏭ Skipped: {$def['title']} (/{$def['slug']} already exists)");
                if ($def['slug'] === 'home') {
                    $homePageModel = $existing;
                }
                $skipped++;
                continue;
            }

            // Reset ordering counters per page
            $this->rowOrder = 0;
            $this->colOrder = 0;
            $this->blockOrder = 0;

            $page = $pageService->createPage([
                'title' => $def['title'],
                'slug' => $def['slug'],
                'status' => 'published',
            ], $site);

            if (!empty($def['blocks'])) {
                $blockService->syncBlocks($page, $def['blocks']);
            }

            if ($def['slug'] === 'home') {
                $homePageModel = $page;
            }

            $this->info("  ✓ Created: {$def['title']} (/{$def['slug']})");
            $created++;
        }

        // Set homepage_id
        if ($homePageModel) {
            $settings = $site->settings ?? [];
            if (empty($settings['homepage_id']) || $settings['homepage_id'] !== $homePageModel->id) {
                $site->update(['settings' => array_merge($settings, [
                    'homepage_id' => $homePageModel->id,
                    'homepage_type' => 'page',
                ])]);
                $this->info("  ✓ Set homepage_id to {$homePageModel->id}");
            }
        }

        $this->newLine();
        $this->info("Done. Created {$created} pages, skipped {$skipped}.");

        return 0;
    }

    // ─── Page definitions ─────────────────────────────────────────────

    private function homePage(): array
    {
        return [
            'title' => 'Home',
            'slug' => 'home',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Build. Publish. Own.',
                                'level' => 'h1',
                                'fontSize' => '3rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', [
                                'content' => '<p>A static-first CMS that publishes pure HTML. No runtime dependencies. No vendor lock-in. Your content, your server, your rules.</p>',
                                'textAlign' => 'center',
                            ]),
                            $this->block('spacer', ['height' => '32px']),
                            $this->block('button', [
                                'text' => 'Explore Features',
                                'url' => '/features',
                                'style' => 'outline',
                                'size' => 'lg',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '80px', 'max_width' => '1200px']),

                // Three pillars
                $this->section([
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Static Output', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Every page publishes as pure HTML + CSS. No PHP, no Node, no database on the client server. Just files.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => '93 Block Types', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Layout, typography, media, marketing, blog, forms, commerce, navigation, data, and interactive blocks — all built in.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Zero Lock-in', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Download your site as a ZIP. Upload via SFTP. Host anywhere. Move anytime. You own every byte.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1100px']),

                // How It Works
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'How It Works', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '24px']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => '1. Design', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Build pages with a visual block editor. Choose from 93 block types. Apply themes with W3C design tokens.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => '2. Build', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>The CMS renders your content into static HTML. Smart diff publishing only rebuilds what changed.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => '3. Deploy', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Publish to any server via SFTP, download as ZIP, or deploy locally. Your hosting, your choice.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Core Features
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Core Features', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '24px']),
                        ]),
                    ]),
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Theme Engine', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>W3C Design Token system with primitive → semantic token architecture. 4 system themes included. Fork, customize, or build from scratch.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Block Editor', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>93 block types across 10 categories. Drag-and-drop. Inline editing. Section → Row → Column → Block hierarchy. Real-time preview.</p>']),
                        ]),
                    ]),
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'AI Assistant', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Claude-powered content generation, rewriting, translation, SEO optimization, and image alt text. Built in, not bolted on.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Magazine DTP', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Full desktop publishing editor. Spreads, frames, layers, master pages. Flipbook output with page-turn animation.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Security
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Security by Architecture', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', [
                                'content' => '<p>Your published site is pure HTML. No server-side code. No database. No attack surface. The CMS runs on your private domain with PostgreSQL Row-Level Security isolating every tenant.</p>',
                                'textAlign' => 'center',
                            ]),
                            $this->block('spacer', ['height' => '32px']),
                            $this->block('button', [
                                'text' => 'Read About Security',
                                'url' => '/features#security',
                                'style' => 'outline',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '800px']),

                // CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('divider', []),
                            $this->block('spacer', ['height' => '40px']),
                            $this->block('heading', ['text' => 'Start Building Today', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', [
                                'content' => '<p>Stillo Press is a CMS that respects your independence. No subscriptions to maintain your site. No platform that owns your content. Just tools that work.</p>',
                                'textAlign' => 'center',
                            ]),
                            $this->block('spacer', ['height' => '32px']),
                            $this->block('button', [
                                'text' => 'Get Started',
                                'url' => '/contact',
                                'style' => 'primary',
                                'size' => 'lg',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1000px']),
            ],
        ];
    }

    private function featuresPage(): array
    {
        return [
            'title' => 'Features',
            'slug' => 'features',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Features',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>Everything you need to build, manage, and deploy professional websites.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // 93 Block Types
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => '93 Block Types', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>The most comprehensive block library available. Layout blocks for any grid. Typography blocks for editorial precision. Media blocks for images, video, galleries, before/after comparisons. Marketing blocks for pricing, testimonials, feature grids. Blog blocks for posts, categories, archives. Form blocks for contact and custom forms. Interactive blocks for accordions, tabs, modals, tooltips. Navigation blocks for menus, breadcrumbs, table of contents.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Theme Engine + Publishing Engine
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Theme Engine', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Built on the W3C Design Token specification. Two-level architecture: primitive tokens (raw values) resolve into semantic tokens (role-based aliases). Every color, font, spacing, radius, and shadow is a token. Change one value, the entire site follows.</p>']),
                            $this->block('paragraph', ['content' => '<p>4 system themes included: Editorial (serif, magazine-first), Commerce (product-focused, vivid), Bare (minimal, system fonts), Cytechno (Swiss/Bauhaus, monochrome). Fork any theme. Override per-site. Version-controlled.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Publishing Engine', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Static-first architecture. Every page renders to pure HTML + CSS files. No server-side runtime on the published site.</p>']),
                            $this->block('paragraph', ['content' => '<p>Three deploy methods: local filesystem, SSH/SFTP to remote servers, ZIP download for manual upload. Smart diff publishing detects changed blocks and only rebuilds affected pages. Full deployment history with one-click rollback.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // AI + SEO
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'AI Assistant', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Powered by Claude. Generate content from prompts with automatic tone-matching from existing page content. Rewrite text with instructions while preserving HTML structure. Translate content to any language. Auto-generate SEO meta (title, description, Open Graph) by analyzing all page blocks. Vision-powered alt text suggestions for images.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'SEO', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Auto-generated meta tags, Open Graph, Twitter Cards. JSON-LD structured data (WebPage, Article, Breadcrumb schemas). XML sitemap generation. robots.txt generation. RSS feed generation. Canonical URLs with custom domain support. Per-page noindex/nofollow controls.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Magazine + Grid
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Magazine / DTP', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Full desktop publishing editor alongside the block editor. Issue-based workflow with spreads, frames, layers, and master pages. Preflight checking before render. Flipbook block with page-turn animation for web output. Canvas-based positioning with x/y/width/height/rotation controls.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Grid System', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>CSS Grid layout system with named positions, responsive breakpoints, and 6-level resolution priority. Grid template areas, track definitions, full-bleed positions. Tablet and mobile overrides. Grid presets for common layouts. Position overrides per-page.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Security
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Security', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Multi-tenant architecture with PostgreSQL Row-Level Security. Every database query is scoped to the current tenant — no data leaks between sites, even with SQL injection.</p>']),
                            $this->block('paragraph', ['content' => '<p>Published sites are static HTML. No server-side code, no database connections, no attack surface. The CMS admin runs on a separate private domain, invisible to site visitors.</p>']),
                            $this->block('paragraph', ['content' => '<p>Content validation and sanitization on every block. URL injection prevention. XSS filtering. CSRF protection. Rate limiting.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px', 'html_id' => 'security']),

                // Import/Export + Developer
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Import / Export', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>WordPress import from WXR XML files. Automatic Gutenberg block conversion. Media/attachment import with URL rewriting. Full site clone (deep copy of pages, posts, blocks, assets, themes, settings). JSON site export.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Developer Features', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>REST API for all resources. Content versioning with block-level snapshots. Collaborative editing with presence awareness. Custom forms with configurable endpoints. HTML embed blocks for custom code. Hook system for head/body script injection.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),
            ],
        ];
    }

    private function aboutPage(): array
    {
        return [
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'About Stillo Press',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>A CMS built on the principle that your website should outlive your tools.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // Philosophy + Architecture
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Philosophy', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Most CMS platforms are landlords. They host your content on their servers, style it with their templates, and charge you rent forever. If you stop paying, your site disappears.</p>']),
                            $this->block('paragraph', ['content' => '<p>Stillo Press is different. It\'s a tool, not a platform. You build your site, publish it as static HTML, and host it anywhere. On any server. With any provider. For as long as you want.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Architecture', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Built with Laravel 13, React 19, and PostgreSQL. The admin interface is a modern single-page application. The published output is pure HTML — no framework, no dependencies, no JavaScript required.</p>']),
                            $this->block('paragraph', ['content' => '<p>Row-Level Security in PostgreSQL ensures complete data isolation between tenants. Every query, every migration, every backup is tenant-scoped by default.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1000px']),

                // What We Believe
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'What We Believe', 'level' => 'h2']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', ['content' => '<p>Content ownership is non-negotiable. Your words, your images, your design — they belong to you. A CMS should be a workshop, not a cage.</p>']),
                            $this->block('paragraph', ['content' => '<p>Static output is the most secure architecture. If there\'s no server-side code on your website, there\'s nothing to hack. No zero-days. No patches. No emergencies.</p>']),
                            $this->block('paragraph', ['content' => '<p>Complexity should be optional. A simple page should be simple to make. Advanced features — DTP, cinematic scroll, AI — are there when you need them, invisible when you don\'t.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1000px']),

                // Timeline
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Timeline', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('timeline', [
                                'layout' => 'alternating',
                                'items' => [
                                    ['date' => '2024', 'title' => 'Project Start', 'description' => 'Initial architecture design. Laravel + PostgreSQL + React stack chosen.'],
                                    ['date' => '2025 Q1', 'title' => 'Block Editor v1', 'description' => 'First 40 block types. Section-row-column hierarchy. Real-time preview.'],
                                    ['date' => '2025 Q2', 'title' => 'Theme Engine', 'description' => 'W3C Design Token system. 4 system themes. Token-to-CSS generation.'],
                                    ['date' => '2025 Q3', 'title' => 'Publishing Pipeline', 'description' => 'Static HTML output. SFTP deploy. Smart diff publishing.'],
                                    ['date' => '2025 Q4', 'title' => 'AI + DTP', 'description' => 'Claude integration. Magazine editor. Flipbook block.'],
                                    ['date' => '2026', 'title' => '93 Blocks + Open Source', 'description' => 'Full block library. Experience mode. Custom cursor. MIT license preparation.'],
                                ],
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '1000px']),
            ],
        ];
    }

    private function demosPage(): array
    {
        return [
            'title' => 'Demos',
            'slug' => 'demos',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Demos',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>See what Stillo Press can build. Every demo below was created using the CMS.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // Live Sites
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Live Sites', 'level' => 'h2']),
                            $this->block('spacer', ['height' => '16px']),
                        ]),
                    ]),
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Ensodo — Wabi-Sabi', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Cinematic experience mode with GSAP ScrollTrigger-driven scene presets. Pinned sections, scroll-gallery crossfade, parallax split, reveal animations. Custom cursor, preloader, ambient sound toggle.</p>']),
                            $this->block('button', [
                                'text' => 'View Demo',
                                'url' => 'https://ensodo.eu/wabisabi4/?experience=force',
                                'style' => 'outline',
                                'target' => '_blank',
                            ]),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Ensodo — Block Showcase', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Same content rebuilt entirely with CMS blocks. Video hero with capsule shape and pre-title. Catalog block with numbered accordion, grayscale image galleries, bilingual text. Button, heading, paragraph blocks.</p>']),
                            $this->block('button', [
                                'text' => 'View Demo',
                                'url' => 'https://ensodo.eu/wabisabi2/',
                                'style' => 'outline',
                                'target' => '_blank',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Starter Templates
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Starter Templates', 'level' => 'h2']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', ['content' => '<p>Every new site starts with a template. Four included: Blank (empty canvas), Blog (posts + categories + archive), Portfolio (gallery + project pages), Business (services + team + contact). Each template creates pages with pre-built block content and sample data.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Block Library
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Block Library', 'level' => 'h2']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', ['content' => '<p>93 blocks organized in 10 categories. Here are the highlights:</p>']),
                            $this->block('spacer', ['height' => '8px']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Layout', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Section, Row, Column, Grid, Container, Group, Overlap, Sticky Sidebar, Spacer, Divider, Fullbleed</p>', 'fontSize' => '0.9rem']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Typography', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Heading, Paragraph, Rich Text, Running Text, Pull Quote, Drop Cap, Caption, Sidenote, Footnote, List, Text Divider, Code</p>', 'fontSize' => '0.9rem']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Media', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Image, Video, Audio, Gallery (grid/masonry/carousel), Before/After, Social Embed, HTML Embed, Map, Chart, Icon</p>', 'fontSize' => '0.9rem']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Marketing', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Hero, Button, CTA Banner, Pricing Card, Pricing Table, Feature Grid, Feature Comparison, Testimonial, Timeline, Stats, Logo Strip</p>', 'fontSize' => '0.9rem']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Blog', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Latest Posts, Post Grid, Post Card, Category List, Author Box, Related Posts, Newsletter, Share Buttons, + 9 template blocks</p>', 'fontSize' => '0.9rem']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Interactive', 'level' => 'h3', 'fontSize' => '1.1rem']),
                            $this->block('paragraph', ['content' => '<p>Tabs, Accordion, Catalog, Modal, Tooltip, Table of Contents, Reading Progress, Anchor Menu, Breadcrumbs, Menu, Flipbook</p>', 'fontSize' => '0.9rem']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),
            ],
        ];
    }

    private function pricingPage(): array
    {
        return [
            'title' => 'Pricing',
            'slug' => 'pricing',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Pricing',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>Simple plans. No surprises. Your published sites are yours forever — even if you cancel.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // Pricing Cards
                $this->section([
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Free', 'level' => 'h3', 'textAlign' => 'center']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center"><strong style="font-size:2rem">$0</strong></p><p style="text-align:center">forever</p>', 'textAlign' => 'center']),
                            $this->block('divider', []),
                            $this->block('paragraph', ['content' => '<p>1 site</p><p>All 93 block types</p><p>Static HTML publish</p><p>ZIP download</p><p>Community support</p>', 'fontSize' => '0.9rem']),
                            $this->block('button', ['text' => 'Get Started', 'url' => '/contact', 'style' => 'outline', 'size' => 'md']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Pro', 'level' => 'h3', 'textAlign' => 'center']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center"><strong style="font-size:2rem">$19</strong></p><p style="text-align:center">/month</p>', 'textAlign' => 'center']),
                            $this->block('divider', []),
                            $this->block('paragraph', ['content' => '<p>5 sites</p><p>All 93 block types</p><p>AI Assistant (Claude)</p><p>SFTP deploy</p><p>Custom domains</p><p>Theme customization</p><p>Priority support</p>', 'fontSize' => '0.9rem']),
                            $this->block('button', ['text' => 'Start Free Trial', 'url' => '/contact', 'style' => 'primary', 'size' => 'md']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Business', 'level' => 'h3', 'textAlign' => 'center']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center"><strong style="font-size:2rem">$49</strong></p><p style="text-align:center">/month</p>', 'textAlign' => 'center']),
                            $this->block('divider', []),
                            $this->block('paragraph', ['content' => '<p>Unlimited sites</p><p>Everything in Pro</p><p>Magazine / DTP editor</p><p>White-label option</p><p>WordPress import</p><p>Site clone + export</p><p>Dedicated support</p>', 'fontSize' => '0.9rem']),
                            $this->block('button', ['text' => 'Contact Us', 'url' => '/contact', 'style' => 'outline', 'size' => 'md']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // FAQ
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Questions', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('accordion', [
                                'items' => [
                                    ['title' => 'What happens to my site if I cancel?', 'content' => '<p>Nothing. Your published site is static HTML on your server. It keeps running. You just lose access to the CMS editor.</p>'],
                                    ['title' => 'Can I switch plans?', 'content' => '<p>Yes, upgrade or downgrade at any time. Changes take effect immediately.</p>'],
                                    ['title' => 'Do you offer annual billing?', 'content' => '<p>Yes. Annual plans include 2 months free.</p>'],
                                    ['title' => 'Is there a free trial?', 'content' => '<p>Pro and Business plans include a 14-day free trial. No credit card required.</p>'],
                                    ['title' => 'Can I self-host the CMS?', 'content' => '<p>Yes. Stillo Press is designed to run on your own server. Laravel 13 + PostgreSQL. Full installation guide in the docs.</p>'],
                                    ['title' => 'What about the AI features?', 'content' => '<p>AI features use Claude by Anthropic. You provide your own API key, or use ours on Pro and Business plans.</p>'],
                                ],
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '800px']),
            ],
        ];
    }

    private function docsPage(): array
    {
        return [
            'title' => 'Documentation',
            'slug' => 'docs',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Documentation',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>Everything you need to install, configure, and use Stillo Press.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // Getting Started
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Getting Started', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p><strong>Requirements:</strong> PHP 8.3+, PostgreSQL 15+, Node.js 20+, Composer, npm.</p>']),
                            $this->block('paragraph', ['content' => '<p><strong>Installation:</strong></p><p>1. Clone the repository</p><p>2. Run <code>composer install</code> and <code>npm install</code></p><p>3. Configure <code>.env</code> with your database credentials</p><p>4. Run <code>php artisan migrate</code></p><p>5. Run <code>php artisan tenant:create</code> to set up your first tenant</p><p>6. Build the admin: <code>npm run build --prefix resources/admin</code></p><p>7. Visit <code>/admin</code> to log in</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '900px']),

                // Creating Your First Site
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Creating Your First Site', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>After logging in, click \'New Site\' on the dashboard. The 4-step wizard guides you through: name your site, choose a theme, pick a starter template (Blank, Blog, Portfolio, or Business), and confirm. Your site is created instantly with default pages and sample content.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Block Editor
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Block Editor', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Every page is built from blocks arranged in a section → row → column → block hierarchy. Click \'+\' to add a block. Drag to reorder. Click a block to edit its content and settings in the sidebar. Changes are saved automatically.</p>']),
                            $this->block('paragraph', ['content' => '<p>Blocks have four settings tabs: Content (block-specific data), Style (colors, padding, background), Animation (entrance effects), Advanced (HTML ID, classes, responsive visibility).</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Themes
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Themes', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Themes use W3C Design Tokens — a standardized format for design decisions. Primitive tokens define raw values (colors, fonts, sizes). Semantic tokens alias primitives into roles (brand, accent, text.body, background.canvas).</p>']),
                            $this->block('paragraph', ['content' => '<p>Four system themes are included. To customize, go to Theme Engine in the admin sidebar. Fork a system theme or create a new one. Every CSS variable on your site updates in real-time.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Publishing & Deploy
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Publishing & Deploy', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Click \'Publish\' in the admin to build your site. The CMS renders every page to static HTML, minifies the output, and deploys to your chosen target.</p>']),
                            $this->block('paragraph', ['content' => '<p><strong>Deploy methods:</strong></p><p>• <strong>Local:</strong> Writes to a directory on the same server (default)</p><p>• <strong>SSH/SFTP:</strong> Uploads to a remote server via rsync over SSH</p><p>• <strong>ZIP:</strong> Generates a downloadable ZIP archive</p>']),
                            $this->block('paragraph', ['content' => '<p>Smart diff publishing detects which pages changed and only rebuilds those. Deployment history with one-click rollback to any previous version.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // API Reference
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'API Reference', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p>Stillo Press exposes a full REST API at <code>/api/v1/</code>. All endpoints require Sanctum authentication. Resources: sites, pages, posts, blocks, categories, tags, menus, assets, themes, grids, templates.</p>']),
                            $this->block('paragraph', ['content' => '<p>Detailed API documentation is coming soon. For now, explore the endpoints in <code>routes/api.php</code>.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '900px']),
            ],
        ];
    }

    private function contactPage(): array
    {
        return [
            'title' => 'Contact',
            'slug' => 'contact',
            'blocks' => [
                // Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', [
                                'text' => 'Contact',
                                'level' => 'h1',
                                'fontSize' => '2.5rem',
                                'textAlign' => 'center',
                                'letterSpacing' => '0.08em',
                                'textTransform' => 'uppercase',
                            ]),
                            $this->block('paragraph', [
                                'content' => '<p>Questions, feedback, or partnership inquiries — we\'d love to hear from you.</p>',
                                'textAlign' => 'center',
                            ]),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '60px', 'max_width' => '800px']),

                // Contact form + info
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'Send a Message', 'level' => 'h2']),
                            $this->block('contact-form', [
                                'recipient_email' => 'hello@stillopress.com',
                                'submit_label' => 'Send',
                                'success_message' => 'Thank you. We\'ll get back to you within 24 hours.',
                                'fields' => [
                                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                                    ['label' => 'Email', 'type' => 'email', 'required' => true],
                                    ['label' => 'Subject', 'type' => 'text', 'required' => false],
                                    ['label' => 'Message', 'type' => 'textarea', 'required' => true],
                                ],
                            ]),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Other Ways', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p><strong>Email</strong><br>hello@stillopress.com</p>']),
                            $this->block('paragraph', ['content' => '<p><strong>GitHub</strong><br>Report issues, contribute code, or browse the source.</p>']),
                            $this->block('paragraph', ['content' => '<p><strong>Documentation</strong><br>Check the <a href="/docs">docs</a> for self-service answers.</p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('paragraph', ['content' => '<p>Stillo Press is developed by Ensodo. Based in Europe.</p>', 'fontSize' => '0.85rem']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '1000px']),
            ],
        ];
    }

    // ─── Block helpers ────────────────────────────────────────────────

    private function section(array $children, array $data = []): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => array_merge([
                'padding_top' => '40px',
                'padding_bottom' => '40px',
                'max_width' => '1200px',
            ], $data),
            'children' => $children,
        ];
    }

    private function row(string $layout, array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'row',
            'level' => 'row',
            'order' => $this->rowOrder++,
            'data' => ['layout' => $layout, 'gap' => '24px'],
            'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'column',
            'level' => 'column',
            'order' => $this->colOrder++,
            'data' => [],
            'children' => $children,
        ];
    }

    private function block(string $type, array $data): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => $this->blockOrder++,
            'data' => $data,
            'children' => [],
        ];
    }
}
