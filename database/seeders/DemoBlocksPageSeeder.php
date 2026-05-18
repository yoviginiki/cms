<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoBlocksPageSeeder extends Seeder
{
    private int $uuidCounter = 0;

    private function uuid(): string
    {
        $this->uuidCounter++;
        return sprintf(
            'demo%04d-0000-0000-0000-%012d',
            $this->uuidCounter,
            $this->uuidCounter
        );
    }

    public function run(): void
    {
        // Set tenant context for RLS
        $user = \App\Models\User::first();
        if ($user && $user->tenant_id) {
            \Illuminate\Support\Facades\DB::unprepared("SET app.current_tenant_id = '{$user->tenant_id}'");
        }

        $site = Site::first();
        if (!$site) {
            $this->command->error("No site found. Create one via the admin UI first.");
            return;
        }

        // Reuse existing page or create new one
        $existing = Page::where('site_id', $site->id)->where('slug', 'block-showcase')->first();
        if ($existing) {
            $existing->blocks()->delete();
            $page = $existing;
            $this->command->info("Cleared existing block-showcase page blocks");
        } else {
            $page = Page::create([
                'site_id' => $site->id,
                'title' => 'Block Showcase',
                'slug' => 'block-showcase',
                'status' => 'published',
                'editor_mode' => 'builder',
                'published_at' => now(),
            ]);
        }

        $sections = $this->buildSections();

        $sectionOrder = 0;
        foreach ($sections as $section) {
            $this->createSection($page, $section, $sectionOrder++);
        }

        $this->command->info("Created 'Block Showcase' page with all 70 blocks on site: {$site->name}");
    }

    private function createSection(Page $page, array $section, int $order): void
    {
        $sectionBlock = Block::create([
            'blockable_id' => $page->id,
            'blockable_type' => 'page',
            'parent_block_id' => null,
            'type' => 'section',
            'level' => 'section',
            'order' => $order,
            'data' => $section['data'],
        ]);

        $rowOrder = 0;
        foreach ($section['rows'] as $row) {
            $rowBlock = Block::create([
                'blockable_id' => $page->id,
                'blockable_type' => 'page',
                'parent_block_id' => $sectionBlock->id,
                'type' => 'row',
                'level' => 'row',
                'order' => $rowOrder++,
                'data' => $row['data'],
            ]);

            $colOrder = 0;
            foreach ($row['columns'] as $column) {
                $colBlock = Block::create([
                    'blockable_id' => $page->id,
                    'blockable_type' => 'page',
                    'parent_block_id' => $rowBlock->id,
                    'type' => 'column',
                    'level' => 'column',
                    'order' => $colOrder++,
                    'data' => $column['data'] ?? ['padding' => '', 'vertical_align' => 'start', 'background_color' => ''],
                ]);

                $modOrder = 0;
                foreach ($column['modules'] as $module) {
                    Block::create([
                        'blockable_id' => $page->id,
                        'blockable_type' => 'page',
                        'parent_block_id' => $colBlock->id,
                        'type' => $module['type'],
                        'level' => 'module',
                        'order' => $modOrder++,
                        'data' => $module['data'],
                    ]);
                }
            }
        }
    }

    private function singleColumnRow(array $modules): array
    {
        return [
            'data' => ['layout' => '1', 'gap' => '32px', 'vertical_align' => 'start'],
            'columns' => [
                ['data' => ['padding' => '', 'vertical_align' => 'start', 'background_color' => ''], 'modules' => $modules],
            ],
        ];
    }

    private function buildSections(): array
    {
        return [
            $this->sectionTypography(),
            $this->sectionMedia(),
            $this->sectionLayout(),
            $this->sectionInteractive(),
            $this->sectionMarketing(),
            $this->sectionBlog(),
            $this->sectionForms(),
            $this->sectionEmbedsAdvanced(),
        ];
    }

    // ─── Section 1: Typography ────────────────────────────────────────────────

    private function sectionTypography(): array
    {
        return [
            'data' => [
                'background_color' => '',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'typography',
            ],
            'rows' => [
                // Section title
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 1: Typography', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // heading
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'The Art of Great Typography', 'level' => 'h2', 'color' => '', 'fontSize' => '2rem', 'fontWeight' => '700', 'lineHeight' => '1.3', 'letterSpacing' => '-0.02em', 'textTransform' => '', 'textAlign' => '']],
                ]),
                // paragraph
                $this->singleColumnRow([
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Typography is the art and technique of arranging type to make written language legible, readable, and appealing when displayed. The arrangement of type involves selecting typefaces, point sizes, line lengths, line-spacing, and letter-spacing.</p>']],
                ]),
                // text
                $this->singleColumnRow([
                    ['type' => 'text', 'data' => ['content' => 'This is a plain text block without any HTML formatting. It renders as a simple text node, useful for minimal content that doesn\'t need rich styling.']],
                ]),
                // rich-text
                $this->singleColumnRow([
                    ['type' => 'rich-text', 'data' => ['content' => '<h3>Rich Text Block</h3><p>This block supports <strong>bold</strong>, <em>italic</em>, <a href="#">links</a>, and more.</p><ul><li>Bullet point one</li><li>Bullet point two</li><li>Bullet point three</li></ul><blockquote>This is a blockquote inside rich text.</blockquote>']],
                ]),
                // runningtext
                $this->singleColumnRow([
                    ['type' => 'runningtext', 'data' => ['content' => '<p>Running text is ideal for long-form editorial content. It supports multi-column layouts that give a newspaper or magazine feel to your content. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>', 'columns' => 2, 'columnGap' => '2rem', 'columnRule' => true]],
                ]),
                // dropcap
                $this->singleColumnRow([
                    ['type' => 'dropcap', 'data' => ['content' => '<p>Once upon a time, in a land of carefully crafted content, there lived a drop cap that drew readers into the story from the very first letter. This classic typographic technique has been used in manuscripts and printed books for centuries.</p>', 'capSize' => 3, 'capColor' => '#3b82f6']],
                ]),
                // pullquote
                $this->singleColumnRow([
                    ['type' => 'pullquote', 'data' => ['text' => 'Design is not just what it looks like and feels like. Design is how it works.', 'attribution' => 'Steve Jobs', 'style' => 'bordered']],
                ]),
                // caption
                $this->singleColumnRow([
                    ['type' => 'caption', 'data' => ['text' => 'A photograph from the summit of Mount Rainier at sunset', 'prefix' => 'Figure 1:']],
                ]),
                // footnote
                $this->singleColumnRow([
                    ['type' => 'footnote', 'data' => ['content' => 'According to the 2024 State of Typography report, variable fonts now account for 34% of web font usage globally.', 'marker' => '1']],
                ]),
                // sidenote
                $this->singleColumnRow([
                    ['type' => 'sidenote', 'data' => ['content' => 'Edward Tufte popularized the sidenote in academic publishing as a less disruptive alternative to footnotes.', 'side' => 'right']],
                ]),
                // list
                $this->singleColumnRow([
                    ['type' => 'list', 'data' => ['items' => ['Semantic HTML structure', 'Accessible color contrast ratios', 'Responsive font sizing', 'Proper heading hierarchy', 'Keyboard navigation support'], 'style' => 'check', 'icon' => '']],
                ]),
                // code
                $this->singleColumnRow([
                    ['type' => 'code', 'data' => ['code' => "function greet(name: string): string {\n  return `Hello, \${name}! Welcome to the block showcase.`;\n}\n\nconsole.log(greet('World'));", 'language' => 'typescript', 'show_line_numbers' => true]],
                ]),
                // textdivider
                $this->singleColumnRow([
                    ['type' => 'textdivider', 'data' => ['style' => 'symbol', 'customSymbol' => '***', 'width' => 'half']],
                ]),
            ],
        ];
    }

    // ─── Section 2: Media ─────────────────────────────────────────────────────

    private function sectionMedia(): array
    {
        return [
            'data' => [
                'background_color' => '#f8fafc',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'media',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 2: Media', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // image
                $this->singleColumnRow([
                    ['type' => 'image', 'data' => ['assetId' => null, 'url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1200', 'alt' => 'Mountain landscape at golden hour', 'caption' => 'Dolomites, Italy — Photo by Luca Bravo', 'size' => 'full']],
                ]),
                // imagecaption
                $this->singleColumnRow([
                    ['type' => 'imagecaption', 'data' => ['src' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800', 'alt' => 'Foggy forest path', 'caption' => 'Morning mist through a Pacific Northwest forest trail', 'captionPosition' => 'below']],
                ]),
                // gallery
                $this->singleColumnRow([
                    ['type' => 'gallery', 'data' => [
                        'images' => [
                            ['src' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=600', 'alt' => 'Valley sunrise', 'caption' => 'Valley at dawn'],
                            ['src' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600', 'alt' => 'Lake reflection', 'caption' => 'Still waters'],
                            ['src' => 'https://images.unsplash.com/photo-1433086966358-54859d0ed716?w=600', 'alt' => 'Waterfall', 'caption' => 'Hidden falls'],
                            ['src' => 'https://images.unsplash.com/photo-1472214103451-9374bd1c798e?w=600', 'alt' => 'Rolling hills', 'caption' => 'Green pastures'],
                            ['src' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?w=600', 'alt' => 'Coastal cliffs', 'caption' => 'Atlantic coast'],
                            ['src' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600', 'alt' => 'Tropical beach', 'caption' => 'Paradise found'],
                        ],
                        'layout' => 'grid',
                        'columns' => 3,
                        'gap' => '12px',
                    ]],
                ]),
                // fullbleed
                $this->singleColumnRow([
                    ['type' => 'fullbleed', 'data' => ['src' => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=1920', 'alt' => 'Starry night sky over mountains', 'overlayText' => 'Explore the Universe', 'overlayPosition' => 'center', 'scrimOpacity' => 40]],
                ]),
                // video
                $this->singleColumnRow([
                    ['type' => 'video', 'data' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'autoplay' => false, 'muted' => true, 'poster' => 'https://images.unsplash.com/photo-1535016120720-40c646be5580?w=1200']],
                ]),
                // audio
                $this->singleColumnRow([
                    ['type' => 'audio', 'data' => ['url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', 'title' => 'Ambient Soundscape', 'artist' => 'SoundHelix']],
                ]),
                // beforeafter
                $this->singleColumnRow([
                    ['type' => 'beforeafter', 'data' => ['beforeSrc' => 'https://images.unsplash.com/photo-1504198453319-5ce911bafcde?w=800', 'afterSrc' => 'https://images.unsplash.com/photo-1504198453319-5ce911bafcde?w=800&sat=-100', 'beforeLabel' => 'Original', 'afterLabel' => 'Edited', 'initialPosition' => 50]],
                ]),
                // icon
                $this->singleColumnRow([
                    ['type' => 'icon', 'data' => ['name' => 'Rocket', 'size' => '64px', 'color' => '#3b82f6']],
                ]),
                // logostrip
                $this->singleColumnRow([
                    ['type' => 'logostrip', 'data' => [
                        'images' => [
                            ['src' => 'https://via.placeholder.com/150x50?text=Acme', 'alt' => 'Acme Corp', 'url' => '#'],
                            ['src' => 'https://via.placeholder.com/150x50?text=Globex', 'alt' => 'Globex', 'url' => '#'],
                            ['src' => 'https://via.placeholder.com/150x50?text=Initech', 'alt' => 'Initech', 'url' => '#'],
                            ['src' => 'https://via.placeholder.com/150x50?text=Hooli', 'alt' => 'Hooli', 'url' => '#'],
                            ['src' => 'https://via.placeholder.com/150x50?text=Pied+Piper', 'alt' => 'Pied Piper', 'url' => '#'],
                        ],
                        'speed' => 30,
                        'pauseOnHover' => true,
                    ]],
                ]),
            ],
        ];
    }

    // ─── Section 3: Layout ────────────────────────────────────────────────────

    private function sectionLayout(): array
    {
        return [
            'data' => [
                'background_color' => '',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'layout',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 3: Layout', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // spacer
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Spacer (lg)', 'level' => 'h4', 'color' => '#6b7280', 'fontSize' => '', 'fontWeight' => '', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => '']],
                    ['type' => 'spacer', 'data' => ['height' => 'lg']],
                    ['type' => 'paragraph', 'data' => ['content' => '<p>The spacer above adds vertical breathing room between content blocks.</p>']],
                ]),
                // divider
                $this->singleColumnRow([
                    ['type' => 'divider', 'data' => ['style' => 'dashed', 'color' => '#cbd5e1', 'thickness' => '2px', 'width' => '80%', 'alignment' => 'center']],
                ]),
                // columns (demonstrated via a 3-col row)
                [
                    'data' => ['layout' => '1/3+1/3+1/3', 'gap' => '24px', 'vertical_align' => 'stretch'],
                    'columns' => [
                        ['data' => ['padding' => '1rem', 'vertical_align' => 'start', 'background_color' => '#eff6ff'], 'modules' => [
                            ['type' => 'columns', 'data' => ['columns' => 3, 'gap' => '16px', 'equalHeight' => true]],
                        ]],
                        ['data' => ['padding' => '1rem', 'vertical_align' => 'start', 'background_color' => '#f0fdf4'], 'modules' => [
                            ['type' => 'paragraph', 'data' => ['content' => '<p><strong>Column 2:</strong> This demonstrates a 3-column row layout with colored backgrounds.</p>']],
                        ]],
                        ['data' => ['padding' => '1rem', 'vertical_align' => 'start', 'background_color' => '#fef3c7'], 'modules' => [
                            ['type' => 'paragraph', 'data' => ['content' => '<p><strong>Column 3:</strong> Each column can hold independent module content.</p>']],
                        ]],
                    ],
                ],
                // container
                $this->singleColumnRow([
                    ['type' => 'container', 'data' => ['maxWidth' => '800px', 'padding' => '2rem', 'background' => '#f1f5f9', 'borderRadius' => '12px']],
                ]),
                // grid
                $this->singleColumnRow([
                    ['type' => 'grid', 'data' => ['columns' => 4, 'gap' => '16px', 'minChildWidth' => '200px']],
                ]),
                // group
                $this->singleColumnRow([
                    ['type' => 'group', 'data' => ['direction' => 'row', 'gap' => '12px', 'align' => 'center', 'wrap' => true]],
                ]),
                // stickysidebar
                $this->singleColumnRow([
                    ['type' => 'stickysidebar', 'data' => ['sidebarSide' => 'right', 'sidebarWidth' => '300px', 'gap' => '2rem', 'stickyOffset' => '80px']],
                ]),
                // overlap
                $this->singleColumnRow([
                    ['type' => 'overlap', 'data' => ['offsetX' => '-20px', 'offsetY' => '-40px', 'zIndex' => 10]],
                ]),
            ],
        ];
    }

    // ─── Section 4: Interactive ───────────────────────────────────────────────

    private function sectionInteractive(): array
    {
        return [
            'data' => [
                'background_color' => '#f8fafc',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'interactive',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 4: Interactive', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // button
                $this->singleColumnRow([
                    ['type' => 'button', 'data' => ['text' => 'Primary Action', 'url' => '/get-started', 'style' => 'primary', 'size' => 'lg', 'target' => '_self', 'iconLeft' => '', 'iconRight' => 'ArrowRight', 'fullWidth' => false]],
                    ['type' => 'button', 'data' => ['text' => 'Secondary', 'url' => '/learn-more', 'style' => 'secondary', 'size' => 'md', 'target' => '_self', 'iconLeft' => '', 'iconRight' => '', 'fullWidth' => false]],
                    ['type' => 'button', 'data' => ['text' => 'Outline Style', 'url' => '#', 'style' => 'outline', 'size' => 'md', 'target' => '_self', 'iconLeft' => 'ExternalLink', 'iconRight' => '', 'fullWidth' => false]],
                    ['type' => 'button', 'data' => ['text' => 'Ghost Button', 'url' => '#', 'style' => 'ghost', 'size' => 'sm', 'target' => '_blank', 'iconLeft' => '', 'iconRight' => '', 'fullWidth' => false]],
                ]),
                // accordion
                $this->singleColumnRow([
                    ['type' => 'accordion', 'data' => [
                        'items' => [
                            ['title' => 'What is the Ensodo CMS?', 'content' => '<p>Ensodo is a modern, block-based content management system built for developers and content creators. It features a visual builder with 70+ content blocks.</p>'],
                            ['title' => 'How does the block system work?', 'content' => '<p>Pages are structured as a hierarchy: Sections contain Rows, Rows contain Columns, and Columns contain Modules. Each module is an independent content block with its own settings.</p>'],
                            ['title' => 'Can I create custom blocks?', 'content' => '<p>Yes! The block system is extensible. You can create custom block definitions with their own React editors and Blade renderers.</p>'],
                            ['title' => 'Is it open source?', 'content' => '<p>Ensodo CMS is released under the MIT license. You can self-host it, modify it, and contribute back to the community.</p>'],
                        ],
                        'multiOpen' => false,
                        'iconStyle' => 'chevron',
                    ]],
                ]),
                // tabs
                $this->singleColumnRow([
                    ['type' => 'tabs', 'data' => ['tab_labels' => ['Overview', 'Features', 'Pricing', 'FAQ'], 'style' => 'underline', 'alignment' => 'start']],
                ]),
                // modal
                $this->singleColumnRow([
                    ['type' => 'modal', 'data' => ['triggerText' => 'Open Demo Modal', 'title' => 'Welcome to the Modal Block', 'size' => 'md']],
                ]),
                // tooltip
                $this->singleColumnRow([
                    ['type' => 'tooltip', 'data' => ['triggerText' => 'Hover over me for a tooltip', 'tooltipText' => 'This is contextual help that appears on hover. Great for explaining UI elements.', 'position' => 'top']],
                ]),
                // toc
                $this->singleColumnRow([
                    ['type' => 'toc', 'data' => ['maxDepth' => 3, 'style' => 'sidebar', 'sticky' => true]],
                ]),
                // readingprogress
                $this->singleColumnRow([
                    ['type' => 'readingprogress', 'data' => ['style' => 'top-bar', 'color' => '#3b82f6', 'height' => '3px']],
                ]),
            ],
        ];
    }

    // ─── Section 5: Marketing ─────────────────────────────────────────────────

    private function sectionMarketing(): array
    {
        return [
            'data' => [
                'background_color' => '',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'marketing',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 5: Marketing', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // hero
                $this->singleColumnRow([
                    ['type' => 'hero', 'data' => [
                        'title' => 'Build Something Beautiful',
                        'subtitle' => 'The modern CMS that grows with your vision. Start free, scale infinitely.',
                        'bg_type' => 'gradient',
                        'bg_color' => '',
                        'bg_gradient_type' => 'linear',
                        'bg_gradient_angle' => 135,
                        'bg_gradient_stops' => [['color' => '#667eea', 'position' => 0], ['color' => '#764ba2', 'position' => 100]],
                        'bg_image' => '',
                        'bg_asset_id' => '',
                        'bg_image_size' => 'cover',
                        'bg_image_position' => 'center center',
                        'bg_image_repeat' => 'no-repeat',
                        'bg_overlay_color' => '#000000',
                        'bg_overlay_opacity' => 0,
                        'bg_scroll_effect' => 'none',
                        'bg_parallax_speed' => 0.5,
                        'headlineTag' => 'h1',
                        'textAlignment' => 'center',
                        'verticalPosition' => 'center',
                        'sectionHeight' => 'md',
                        'contentMaxWidth' => '700px',
                        'headlineSize' => '3rem',
                        'headlineWeight' => '800',
                        'headlineColor' => '#ffffff',
                        'subheadlineSize' => '1.25rem',
                        'adaptiveTextColor' => true,
                        'ctaText' => 'Start Building Free',
                        'ctaUrl' => '/signup',
                        'ctaVariant' => 'filled',
                        'ctaSize' => 'lg',
                        'ctaAlign' => '',
                        'ctaBgColor' => '#ffffff',
                        'ctaTextColor' => '#667eea',
                        'ctaBorderColor' => '',
                        'ctaBorderWidth' => '',
                        'ctaBorderRadius' => '0.5rem',
                        'sectionBorderWidth' => '',
                        'sectionBorderColor' => '',
                        'sectionBorderStyle' => '',
                        'sectionBorderRadius' => '12px',
                        'sectionShadow' => 'medium',
                        'sectionShadowMode' => 'preset',
                        'sectionShadowCustom' => [],
                        'contentBoxEnabled' => false,
                        'contentBoxBgColor' => '#ffffff',
                        'contentBoxOpacity' => 80,
                        'contentBoxBorderRadius' => '0.75rem',
                        'contentBoxBorderColor' => '',
                        'contentBoxBorderWidth' => '',
                        'contentBoxShadow' => '',
                        'contentBoxPadding' => '2rem',
                        'alt' => '',
                        'mediaLoading' => 'eager',
                    ]],
                ]),
                // ctabanner
                $this->singleColumnRow([
                    ['type' => 'ctabanner', 'data' => [
                        'heading' => 'Ready to transform your website?',
                        'text' => 'Join 10,000+ creators who build faster with Ensodo. No credit card required.',
                        'buttonText' => 'Start Free Trial',
                        'buttonUrl' => '/trial',
                        'backgroundStyle' => 'gradient',
                        'backgroundColor' => '#1e40af',
                        'backgroundImage' => '',
                    ]],
                ]),
                // pricingcard
                [
                    'data' => ['layout' => '1/3+1/3+1/3', 'gap' => '24px', 'vertical_align' => 'stretch'],
                    'columns' => [
                        ['data' => ['padding' => '', 'vertical_align' => 'start', 'background_color' => ''], 'modules' => [
                            ['type' => 'pricingcard', 'data' => [
                                'planName' => 'Starter',
                                'price' => '$0',
                                'period' => 'month',
                                'features' => [
                                    ['text' => '1 website', 'included' => true],
                                    ['text' => '5 pages', 'included' => true],
                                    ['text' => 'Community support', 'included' => true],
                                    ['text' => 'Custom domain', 'included' => false],
                                    ['text' => 'Analytics', 'included' => false],
                                ],
                                'ctaText' => 'Get Started',
                                'ctaUrl' => '/signup?plan=starter',
                                'highlighted' => false,
                                'badge' => '',
                            ]],
                        ]],
                        ['data' => ['padding' => '', 'vertical_align' => 'start', 'background_color' => ''], 'modules' => [
                            ['type' => 'pricingcard', 'data' => [
                                'planName' => 'Professional',
                                'price' => '$29',
                                'period' => 'month',
                                'features' => [
                                    ['text' => 'Unlimited websites', 'included' => true],
                                    ['text' => 'Unlimited pages', 'included' => true],
                                    ['text' => 'Priority support', 'included' => true],
                                    ['text' => 'Custom domain', 'included' => true],
                                    ['text' => 'Advanced analytics', 'included' => true],
                                ],
                                'ctaText' => 'Go Pro',
                                'ctaUrl' => '/signup?plan=pro',
                                'highlighted' => true,
                                'badge' => 'Most Popular',
                            ]],
                        ]],
                        ['data' => ['padding' => '', 'vertical_align' => 'start', 'background_color' => ''], 'modules' => [
                            ['type' => 'pricingcard', 'data' => [
                                'planName' => 'Enterprise',
                                'price' => '$99',
                                'period' => 'month',
                                'features' => [
                                    ['text' => 'Everything in Pro', 'included' => true],
                                    ['text' => 'SSO & SAML', 'included' => true],
                                    ['text' => 'Dedicated account manager', 'included' => true],
                                    ['text' => 'SLA guarantee', 'included' => true],
                                    ['text' => 'Custom integrations', 'included' => true],
                                ],
                                'ctaText' => 'Contact Sales',
                                'ctaUrl' => '/contact',
                                'highlighted' => false,
                                'badge' => '',
                            ]],
                        ]],
                    ],
                ],
                // pricingtable
                $this->singleColumnRow([
                    ['type' => 'pricingtable', 'data' => [
                        'plans' => [
                            ['name' => 'Free', 'price' => '$0', 'period' => 'forever'],
                            ['name' => 'Pro', 'price' => '$29', 'period' => '/mo'],
                            ['name' => 'Business', 'price' => '$79', 'period' => '/mo'],
                        ],
                        'features' => [
                            ['name' => 'Pages', 'values' => ['5', 'Unlimited', 'Unlimited']],
                            ['name' => 'Storage', 'values' => ['1 GB', '50 GB', '500 GB']],
                            ['name' => 'Custom Domain', 'values' => ['No', 'Yes', 'Yes']],
                            ['name' => 'SSL Certificate', 'values' => ['Shared', 'Dedicated', 'Dedicated']],
                            ['name' => 'Support', 'values' => ['Community', 'Email', '24/7 Phone']],
                        ],
                        'columns' => 3,
                    ]],
                ]),
                // testimonial
                $this->singleColumnRow([
                    ['type' => 'testimonial', 'data' => [
                        'items' => [
                            ['quote' => 'Ensodo completely transformed how we manage content. The block editor is intuitive and powerful.', 'author' => 'Sarah Chen', 'role' => 'Head of Marketing, TechCorp', 'avatar' => ''],
                            ['quote' => 'We migrated from WordPress in a weekend. The developer experience is miles ahead.', 'author' => 'Marcus Rodriguez', 'role' => 'CTO, StartupXYZ', 'avatar' => ''],
                            ['quote' => 'The theming system saved us weeks of work. CSS variables just work.', 'author' => 'Aisha Patel', 'role' => 'Lead Designer, CreativeStudio', 'avatar' => ''],
                        ],
                        'layout' => 'grid',
                    ]],
                ]),
                // featuregrid
                $this->singleColumnRow([
                    ['type' => 'featuregrid', 'data' => [
                        'items' => [
                            ['icon' => 'Zap', 'title' => 'Lightning Fast', 'description' => 'Built on Laravel with optimized queries and edge caching for sub-100ms responses.'],
                            ['icon' => 'Shield', 'title' => 'Secure by Default', 'description' => 'Role-based access, CSRF protection, input sanitization, and SOC2 compliance ready.'],
                            ['icon' => 'Palette', 'title' => 'Beautiful Themes', 'description' => 'CSS variable-based theming with 27 block Blade templates that adapt automatically.'],
                            ['icon' => 'Code2', 'title' => 'Developer Friendly', 'description' => 'REST API, webhooks, custom blocks, and full TypeScript support in the admin.'],
                            ['icon' => 'Globe', 'title' => 'Multi-site Ready', 'description' => 'Manage dozens of sites from one dashboard with isolated content and shared assets.'],
                            ['icon' => 'Sparkles', 'title' => 'AI-Powered', 'description' => 'Generate pages, optimize SEO, and auto-translate content with built-in AI tools.'],
                        ],
                        'columns' => 3,
                        'style' => 'cards',
                    ]],
                ]),
                // featurecomparison
                $this->singleColumnRow([
                    ['type' => 'featurecomparison', 'data' => [
                        'plans' => [
                            ['name' => 'Ensodo', 'highlighted' => true],
                            ['name' => 'WordPress', 'highlighted' => false],
                            ['name' => 'Webflow', 'highlighted' => false],
                        ],
                        'features' => [
                            ['name' => 'Block Editor', 'values' => [true, true, true]],
                            ['name' => 'Self-hosted', 'values' => [true, true, false]],
                            ['name' => 'API-first', 'values' => [true, false, false]],
                            ['name' => 'TypeScript Admin', 'values' => [true, false, false]],
                            ['name' => 'Visual Theme Editor', 'values' => [true, false, true]],
                        ],
                    ]],
                ]),
                // stats
                $this->singleColumnRow([
                    ['type' => 'stats', 'data' => [
                        'items' => [
                            ['value' => '10,000', 'label' => 'Active Users', 'prefix' => '', 'suffix' => '+'],
                            ['value' => '99.9', 'label' => 'Uptime', 'prefix' => '', 'suffix' => '%'],
                            ['value' => '70', 'label' => 'Content Blocks', 'prefix' => '', 'suffix' => ''],
                            ['value' => '4.9', 'label' => 'User Rating', 'prefix' => '', 'suffix' => '/5'],
                        ],
                        'columns' => 4,
                    ]],
                ]),
                // timeline
                $this->singleColumnRow([
                    ['type' => 'timeline', 'data' => [
                        'items' => [
                            ['date' => '2024 Q1', 'title' => 'Project Launch', 'description' => 'Initial release with 30 core blocks and the visual builder.'],
                            ['date' => '2024 Q2', 'title' => 'Theme Engine', 'description' => 'CSS variable-based theming with design tokens and live preview.'],
                            ['date' => '2024 Q3', 'title' => 'Block Stabilization', 'description' => '68 blocks fully stabilized with shared props and inline editing.'],
                            ['date' => '2024 Q4', 'title' => 'Builder UX Overhaul', 'description' => 'Module picker, persistent add buttons, and keyboard shortcuts.'],
                            ['date' => '2025 Q1', 'title' => 'Open Source Release', 'description' => 'MIT license, community contributions, and plugin marketplace.'],
                        ],
                        'layout' => 'alternate',
                    ]],
                ]),
            ],
        ];
    }

    // ─── Section 6: Blog ──────────────────────────────────────────────────────

    private function sectionBlog(): array
    {
        return [
            'data' => [
                'background_color' => '#f8fafc',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'blog',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 6: Blog & Editorial', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // postgrid
                $this->singleColumnRow([
                    ['type' => 'postgrid', 'data' => ['categoryId' => '', 'limit' => 6, 'columns' => 3, 'cardStyle' => 'vertical', 'showExcerpt' => true]],
                ]),
                // latestposts
                $this->singleColumnRow([
                    ['type' => 'latestposts', 'data' => ['count' => 5, 'categoryId' => '', 'showExcerpt' => true, 'showImage' => true]],
                ]),
                // postcard
                $this->singleColumnRow([
                    ['type' => 'postcard', 'data' => ['postId' => '', 'style' => 'horizontal', 'showExcerpt' => true, 'showDate' => true, 'showCategory' => true]],
                ]),
                // categorylist
                $this->singleColumnRow([
                    ['type' => 'categorylist', 'data' => ['style' => 'cards', 'showCount' => true, 'parentOnly' => false]],
                ]),
                // authorbox
                $this->singleColumnRow([
                    ['type' => 'authorbox', 'data' => ['showAvatar' => true, 'showBio' => true, 'showSocialLinks' => true, 'layout' => 'horizontal']],
                ]),
                // relatedposts
                $this->singleColumnRow([
                    ['type' => 'relatedposts', 'data' => ['limit' => 3, 'basedOn' => 'category']],
                ]),
                // newsletter
                $this->singleColumnRow([
                    ['type' => 'newsletter', 'data' => ['heading' => 'Stay in the Loop', 'description' => 'Get weekly insights on web development, design trends, and CMS best practices delivered to your inbox.', 'buttonText' => 'Subscribe Now', 'endpoint' => '', 'style' => 'inline']],
                ]),
                // sharebuttons
                $this->singleColumnRow([
                    ['type' => 'sharebuttons', 'data' => ['platforms' => ['twitter', 'facebook', 'linkedin', 'email', 'copy'], 'style' => 'icons', 'showLabels' => true]],
                ]),
                // paywall
                $this->singleColumnRow([
                    ['type' => 'paywall', 'data' => ['heading' => 'Premium Content', 'description' => 'Subscribe to unlock this article and get full access to all premium content.', 'ctaText' => 'Unlock for $5/month', 'ctaUrl' => '/subscribe']],
                ]),
            ],
        ];
    }

    // ─── Section 7: Forms ─────────────────────────────────────────────────────

    private function sectionForms(): array
    {
        return [
            'data' => [
                'background_color' => '',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'forms',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 7: Forms', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // contact-form
                $this->singleColumnRow([
                    ['type' => 'contact-form', 'data' => [
                        'fields' => [
                            ['label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['label' => 'Email Address', 'type' => 'email', 'required' => true],
                            ['label' => 'Phone Number', 'type' => 'tel', 'required' => false],
                            ['label' => 'Subject', 'type' => 'text', 'required' => true],
                            ['label' => 'Message', 'type' => 'textarea', 'required' => true],
                        ],
                        'submitText' => 'Send Message',
                        'recipientEmail' => 'hello@example.com',
                    ]],
                ]),
                // customform
                $this->singleColumnRow([
                    ['type' => 'customform', 'data' => [
                        'fields' => [
                            ['type' => 'text', 'label' => 'Company Name', 'required' => true, 'placeholder' => 'Acme Inc.', 'options' => null],
                            ['type' => 'email', 'label' => 'Work Email', 'required' => true, 'placeholder' => 'you@company.com', 'options' => null],
                            ['type' => 'select', 'label' => 'Team Size', 'required' => true, 'placeholder' => 'Select...', 'options' => ['1-10', '11-50', '51-200', '200+']],
                            ['type' => 'select', 'label' => 'Interest', 'required' => false, 'placeholder' => 'What interests you?', 'options' => ['CMS Platform', 'API Access', 'Enterprise Support', 'Custom Development']],
                            ['type' => 'textarea', 'label' => 'Tell us about your project', 'required' => false, 'placeholder' => 'Describe your needs...', 'options' => null],
                            ['type' => 'checkbox', 'label' => 'I agree to the privacy policy', 'required' => true, 'placeholder' => '', 'options' => null],
                        ],
                        'submitText' => 'Request Demo',
                    ]],
                ]),
            ],
        ];
    }

    // ─── Section 8: Embeds & Advanced ─────────────────────────────────────────

    private function sectionEmbedsAdvanced(): array
    {
        return [
            'data' => [
                'background_color' => '#f8fafc',
                'background_image' => '',
                'padding_top' => '60px',
                'padding_bottom' => '60px',
                'max_width' => '1200px',
                'anchor_id' => 'embeds-advanced',
            ],
            'rows' => [
                $this->singleColumnRow([
                    ['type' => 'heading', 'data' => ['text' => 'Section 8: Embeds & Advanced', 'level' => 'h1', 'color' => '#1a1a2e', 'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '', 'textTransform' => '', 'textAlign' => 'center']],
                ]),
                // html-embed
                $this->singleColumnRow([
                    ['type' => 'html-embed', 'data' => ['code' => '<div style="padding:2rem;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;color:white;text-align:center;"><h3 style="margin:0 0 0.5rem">Custom HTML Embed</h3><p style="margin:0;opacity:0.9">This is raw HTML rendered inside a sandboxed container. You can embed widgets, custom scripts, or third-party integrations.</p></div>', 'sandbox' => true]],
                ]),
                // socialembed
                $this->singleColumnRow([
                    ['type' => 'socialembed', 'data' => ['url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'platform' => 'youtube']],
                ]),
                // map
                $this->singleColumnRow([
                    ['type' => 'map', 'data' => ['address' => '1600 Amphitheatre Parkway, Mountain View, CA', 'zoom' => 14, 'height' => '400px', 'style' => 'roadmap']],
                ]),
                // chart
                $this->singleColumnRow([
                    ['type' => 'chart', 'data' => [
                        'chartType' => 'bar',
                        'data' => [
                            ['label' => 'Jan', 'value' => 65],
                            ['label' => 'Feb', 'value' => 78],
                            ['label' => 'Mar', 'value' => 90],
                            ['label' => 'Apr', 'value' => 81],
                            ['label' => 'May', 'value' => 95],
                            ['label' => 'Jun', 'value' => 110],
                        ],
                        'title' => 'Monthly Active Users (thousands)',
                        'showLegend' => true,
                    ]],
                ]),
                // table
                $this->singleColumnRow([
                    ['type' => 'table', 'data' => [
                        'headers' => ['Feature', 'Free', 'Pro', 'Enterprise'],
                        'rows' => [
                            ['Pages', '5', 'Unlimited', 'Unlimited'],
                            ['Storage', '1 GB', '50 GB', '500 GB'],
                            ['Custom Domains', 'No', '1', 'Unlimited'],
                            ['API Access', 'Read only', 'Full', 'Full + Webhooks'],
                            ['Support', 'Community', 'Email (48h)', '24/7 Dedicated'],
                        ],
                        'striped' => true,
                        'compact' => false,
                    ]],
                ]),
                // flipbook
                $this->singleColumnRow([
                    ['type' => 'flipbook', 'data' => ['mode' => 'pdf', 'aspect_ratio' => '4:3', 'pdf_asset_id' => '']],
                ]),
                // scroll_page
                $this->singleColumnRow([
                    ['type' => 'scroll_page', 'data' => ['sections' => [['title' => 'Chapter 1', 'content' => 'Introduction to scroll-based storytelling'], ['title' => 'Chapter 2', 'content' => 'Building immersive experiences']], 'transition' => 'fade', 'snap' => true]],
                ]),
                // menu
                $this->singleColumnRow([
                    ['type' => 'menu', 'data' => ['menuId' => '', 'style' => 'horizontal', 'orientation' => 'horizontal']],
                ]),
                // breadcrumbs
                $this->singleColumnRow([
                    ['type' => 'breadcrumbs', 'data' => ['separator' => '/', 'showHome' => true, 'homeLabel' => 'Home', 'showCurrent' => true]],
                ]),
                // anchormenu
                $this->singleColumnRow([
                    ['type' => 'anchormenu', 'data' => [
                        'items' => [
                            ['label' => 'Typography', 'anchor' => '#typography'],
                            ['label' => 'Media', 'anchor' => '#media'],
                            ['label' => 'Layout', 'anchor' => '#layout'],
                            ['label' => 'Interactive', 'anchor' => '#interactive'],
                            ['label' => 'Marketing', 'anchor' => '#marketing'],
                            ['label' => 'Blog', 'anchor' => '#blog'],
                            ['label' => 'Forms', 'anchor' => '#forms'],
                            ['label' => 'Embeds', 'anchor' => '#embeds-advanced'],
                        ],
                        'style' => 'pills',
                        'sticky' => true,
                    ]],
                ]),
            ],
        ];
    }
}
