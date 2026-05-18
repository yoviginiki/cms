<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\ThemeTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $siteId = $this->command->argument('site') ?? null;
        if (!$siteId) {
            // Find first site in tenant context
            $site = DB::table('sites')->first();
            if (!$site) {
                $this->command->error('No sites found');
                return;
            }
            $siteId = $site->id;
        }

        $this->createStandardNewsTemplate($siteId);
        $this->createVideoTemplate($siteId);
        $this->createAudioTemplate($siteId);
        $this->createGalleryTemplate($siteId);
        $this->createCardTemplate($siteId);
        $this->createDefaultArchiveTemplate($siteId);

        $this->command->info('Created 6 post templates with blocks');
    }

    private function createStandardNewsTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Standard News',
            'slug' => 'standard-news',
            'type' => 'post',
            'post_format' => null,
            'is_default' => true,
            'priority' => 0,
            'settings' => [],
        ]);

        // Section wrapper
        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'post',
            'padding_top' => '60px',
            'padding_bottom' => '60px',
            'max_width' => '800px',
        ]);

        // Row > Column > blocks
        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, []);

        // Post Image (full width hero)
        $this->block($template, 'post-image', $col->id, 0, [
            'size' => 'full',
            'aspectRatio' => '16/9',
            'borderRadius' => '8px',
            'objectFit' => 'cover',
        ]);

        // Post Meta
        $this->block($template, 'post-meta', $col->id, 1, [
            'showDate' => true,
            'showAuthor' => true,
            'showCategory' => true,
            'separator' => '·',
            'textAlign' => '',
        ], ['spacing' => ['marginTop' => '24px', 'marginBottom' => '8px']]);

        // Post Title
        $this->block($template, 'post-title', $col->id, 2, [
            'tag' => 'h1',
            'fontSize' => '2.5rem',
            'fontWeight' => '700',
            'color' => '',
            'textAlign' => '',
        ]);

        // Post Excerpt
        $this->block($template, 'post-excerpt', $col->id, 3, [
            'fontSize' => '1.125rem',
            'color' => '',
            'textAlign' => '',
            'maxLines' => 0,
        ], ['spacing' => ['marginBottom' => '32px']]);

        // Post Content
        $this->block($template, 'post-content', $col->id, 4, []);

        // Post Navigation
        $this->block($template, 'post-navigation', $col->id, 5, [
            'showLabels' => true,
            'style' => 'minimal',
        ]);
    }

    private function createVideoTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Video Post',
            'slug' => 'video-post',
            'type' => 'post',
            'post_format' => 'video',
            'is_default' => false,
            'priority' => 10,
            'settings' => [],
        ]);

        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'video-post',
            'padding_top' => '0',
            'padding_bottom' => '60px',
            'max_width' => '1080px',
        ]);

        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, []);

        // Video first (full width, no padding on top)
        $this->block($template, 'post-video', $col->id, 0, [
            'aspectRatio' => '16:9',
            'autoplay' => false,
            'controls' => true,
        ]);

        // Content wrapper with padding
        $row2 = $this->block($template, 'row', $section->id, 1, []);
        $col2 = $this->block($template, 'column', $row2->id, 0, [], [
            'layout' => ['maxWidth' => '800px'],
            'spacing' => ['marginLeft' => 'auto', 'marginRight' => 'auto', 'paddingTop' => '32px'],
        ]);

        // Title
        $this->block($template, 'post-title', $col2->id, 0, [
            'tag' => 'h1',
            'fontSize' => '2rem',
            'fontWeight' => '700',
        ]);

        // Meta
        $this->block($template, 'post-meta', $col2->id, 1, [
            'showDate' => true,
            'showAuthor' => true,
            'showCategory' => true,
            'separator' => '·',
        ], ['spacing' => ['marginBottom' => '24px']]);

        // Content
        $this->block($template, 'post-content', $col2->id, 2, []);

        // Navigation
        $this->block($template, 'post-navigation', $col2->id, 3, [
            'showLabels' => true,
            'style' => 'buttons',
        ]);
    }

    private function createAudioTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Audio Post',
            'slug' => 'audio-post',
            'type' => 'post',
            'post_format' => 'audio',
            'is_default' => false,
            'priority' => 10,
            'settings' => [],
        ]);

        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'audio-post',
            'padding_top' => '60px',
            'padding_bottom' => '60px',
            'max_width' => '700px',
        ]);

        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, []);

        // Small thumbnail
        $this->block($template, 'post-image', $col->id, 0, [
            'size' => 'thumbnail',
            'aspectRatio' => '1/1',
            'borderRadius' => '12px',
            'objectFit' => 'cover',
        ], ['layout' => ['maxWidth' => '200px'], 'spacing' => ['marginLeft' => 'auto', 'marginRight' => 'auto']]);

        // Title (centered)
        $this->block($template, 'post-title', $col->id, 1, [
            'tag' => 'h1',
            'fontSize' => '1.75rem',
            'fontWeight' => '700',
            'textAlign' => 'center',
        ], ['spacing' => ['marginTop' => '24px']]);

        // Meta (centered)
        $this->block($template, 'post-meta', $col->id, 2, [
            'showDate' => true,
            'showAuthor' => true,
            'showCategory' => false,
            'separator' => '·',
            'textAlign' => 'center',
        ], ['spacing' => ['marginBottom' => '32px']]);

        // Content (show notes, transcript)
        $this->block($template, 'post-content', $col->id, 3, []);

        // Navigation
        $this->block($template, 'post-navigation', $col->id, 4, [
            'showLabels' => true,
            'style' => 'minimal',
        ]);
    }

    private function createGalleryTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Gallery Post',
            'slug' => 'gallery-post',
            'type' => 'post',
            'post_format' => 'gallery',
            'is_default' => false,
            'priority' => 10,
            'settings' => [],
        ]);

        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'gallery-post',
            'padding_top' => '40px',
            'padding_bottom' => '60px',
            'max_width' => '1200px',
        ]);

        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, []);

        // Title
        $this->block($template, 'post-title', $col->id, 0, [
            'tag' => 'h1',
            'fontSize' => '2.25rem',
            'fontWeight' => '700',
            'textAlign' => 'center',
        ]);

        // Meta (centered)
        $this->block($template, 'post-meta', $col->id, 1, [
            'showDate' => true,
            'showAuthor' => true,
            'showCategory' => true,
            'separator' => '·',
            'textAlign' => 'center',
        ], ['spacing' => ['marginBottom' => '32px']]);

        // Full-width image
        $this->block($template, 'post-image', $col->id, 2, [
            'size' => 'full',
            'aspectRatio' => '3/2',
            'borderRadius' => '0',
            'objectFit' => 'cover',
        ]);

        // Content (gallery blocks from the post)
        $this->block($template, 'post-content', $col->id, 3, [],
            ['spacing' => ['marginTop' => '32px']]);

        // Navigation
        $this->block($template, 'post-navigation', $col->id, 4, [
            'showLabels' => true,
            'style' => 'full',
        ]);
    }

    private function createCardTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Link Card',
            'slug' => 'link-card',
            'type' => 'post',
            'post_format' => 'link',
            'is_default' => false,
            'priority' => 10,
            'settings' => [],
        ]);

        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'link-post',
            'padding_top' => '80px',
            'padding_bottom' => '80px',
            'max_width' => '600px',
        ]);

        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, [], [
            'visual' => ['backgroundColor' => '#f8f9fa', 'borderWidth' => '1px', 'borderColor' => '#e9ecef', 'borderStyle' => 'solid'],
            'spacing' => ['paddingTop' => '40px', 'paddingRight' => '40px', 'paddingBottom' => '40px', 'paddingLeft' => '40px'],
        ]);

        // Image as card thumbnail
        $this->block($template, 'post-image', $col->id, 0, [
            'size' => 'thumbnail',
            'aspectRatio' => '16/9',
            'borderRadius' => '6px',
            'objectFit' => 'cover',
        ]);

        // Title
        $this->block($template, 'post-title', $col->id, 1, [
            'tag' => 'h2',
            'fontSize' => '1.5rem',
            'fontWeight' => '600',
            'textAlign' => 'center',
        ], ['spacing' => ['marginTop' => '20px']]);

        // Excerpt
        $this->block($template, 'post-excerpt', $col->id, 2, [
            'fontSize' => '1rem',
            'color' => '#666',
            'textAlign' => 'center',
            'maxLines' => 3,
        ]);

        // Meta
        $this->block($template, 'post-meta', $col->id, 3, [
            'showDate' => true,
            'showAuthor' => false,
            'showCategory' => true,
            'separator' => '·',
            'textAlign' => 'center',
        ]);
    }

    private function createDefaultArchiveTemplate(string $siteId): void
    {
        $template = ThemeTemplate::create([
            'site_id' => $siteId,
            'name' => 'Default Archive',
            'slug' => 'default-archive',
            'type' => 'archive',
            'is_default' => true,
            'priority' => 0,
            'settings' => [],
        ]);

        $section = $this->block($template, 'section', null, 0, [
            'anchor_id' => 'archive',
            'padding_top' => '60px',
            'padding_bottom' => '60px',
            'max_width' => '1080px',
        ]);

        $row = $this->block($template, 'row', $section->id, 0, []);
        $col = $this->block($template, 'column', $row->id, 0, []);

        // Category Header
        $this->block($template, 'category-header', $col->id, 0, [
            'showDescription' => true,
            'showPostCount' => true,
            'titleTag' => 'h1',
            'titleSize' => '2.5rem',
            'textAlign' => 'center',
        ], ['spacing' => ['marginBottom' => '40px']]);

        // Post Loop
        $this->block($template, 'post-loop', $col->id, 1, [
            'layout' => 'cards',
            'columns' => 3,
            'showImage' => true,
            'showExcerpt' => true,
            'showDate' => true,
            'showCategory' => false,
            'showAuthor' => false,
            'imageAspectRatio' => '16:9',
            'excerptLines' => 3,
            'gap' => '1.5rem',
            'limit' => 12,
        ]);

        // Pagination
        $this->block($template, 'archive-pagination', $col->id, 2, [
            'style' => 'numbered',
            'align' => 'center',
        ], ['spacing' => ['marginTop' => '40px']]);
    }

    private function block(ThemeTemplate $template, string $type, ?string $parentId, int $order, array $data, array $style = []): Block
    {
        return Block::create([
            'blockable_id' => $template->id,
            'blockable_type' => 'template',
            'parent_block_id' => $parentId,
            'type' => $type,
            'level' => match ($type) {
                'section' => 'section',
                'row' => 'row',
                'column' => 'column',
                default => 'module',
            },
            'data' => $data,
            'style' => $style,
            'order' => $order,
        ]);
    }
}
