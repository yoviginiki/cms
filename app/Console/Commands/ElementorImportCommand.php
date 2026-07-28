<?php

namespace App\Console\Commands;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Database\RlsManager;
use App\Models\Page;
use App\Models\Site;
use App\Services\SiteWizard\ElementorTreeCompiler;
use App\Services\SiteWizard\SiteWizardMediaImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Imports Elementor-built WordPress pages STRAIGHT from a WP database into an
 * existing site's pages as native block trees (widget-by-widget mapping via
 * ElementorTreeCompiler — no DOM heuristics). Pages are matched by slug: an
 * existing page with the same slug gets its block tree REPLACED, a missing one
 * is created as draft.
 *
 *   php artisan elementor:import --site=<uuid|slug> --wp-db=cytechno_vioiv \
 *     --wp-user=... --wp-pass=... --wp-prefix=wpv3_ --pages=4967:home,187:about-us-2
 *
 * --pages maps WP post IDs to target CMS slugs (id:slug, comma-separated).
 */
class ElementorImportCommand extends Command
{
    protected $signature = 'elementor:import
        {--site= : Target site UUID or slug}
        {--tenant= : Tenant UUID (needed for RLS before the site lookup)}
        {--wp-db= : WordPress database name}
        {--wp-user= : WordPress database user}
        {--wp-pass= : WordPress database password}
        {--wp-host=127.0.0.1 : WordPress database host}
        {--wp-prefix=wp_ : WordPress table prefix}
        {--pages= : WP post id:target slug pairs, comma separated}
        {--origin= : Source site base URL (for media referenced only by path)}
        {--catalog-post=0 : WP post ID whose markup lists the category tiles}
        {--posts=0 : Import the N latest source posts as CMS posts}
        {--posts-lang= : Only source posts in this Polylang language (e.g. bg)}
        {--posts-exclude= : Source post IDs to skip (comma separated)}
        {--publish : Publish imported pages instead of leaving drafts}';

    protected $description = 'Import Elementor pages from a WordPress DB as native block trees';

    public function handle(
        ElementorTreeCompiler $compiler,
        BlockService $blocks,
        SiteWizardMediaImporter $media,
    ): int {
        if ($this->option('tenant')) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->option('tenant')]);
        }
        $target = (string) $this->option('site');
        $site = preg_match('/^[0-9a-f-]{36}$/i', $target) === 1
            ? Site::find($target)
            : Site::where('slug', $target)->first();
        if (!$site) {
            $this->error('Site not found: ' . $this->option('site'));

            return self::FAILURE;
        }
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$site->tenant_id]);

        Config::set('database.connections.wp_import', [
            'driver' => 'mysql',
            'host' => $this->option('wp-host'),
            'database' => $this->option('wp-db'),
            'username' => $this->option('wp-user'),
            'password' => $this->option('wp-pass'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $wp = DB::connection('wp_import');
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->option('wp-prefix'));

        // Elementor kit global colors (dark section backgrounds, accents…)
        // and system typography (drives the auto type-scale recipe below).
        $globalColors = [];
        $kitTypography = [];
        $kitId = (int) ($wp->table("{$prefix}options")->where('option_name', 'elementor_active_kit')->value('option_value') ?? 0);
        if ($kitId > 0) {
            $raw = (string) $wp->table("{$prefix}postmeta")->where('post_id', $kitId)
                ->where('meta_key', '_elementor_page_settings')->value('meta_value');
            $settings = @unserialize($raw, ['allowed_classes' => false]);
            foreach (['system_colors', 'custom_colors'] as $key) {
                foreach ((array) ($settings[$key] ?? []) as $c) {
                    if (!empty($c['_id']) && !empty($c['color'])) {
                        $globalColors[$c['_id']] = $c['color'];
                    }
                }
            }
            foreach ((array) ($settings['system_typography'] ?? []) as $t) {
                if (!empty($t['_id'])) {
                    $kitTypography[$t['_id']] = $t;
                }
            }
            $this->line('kit colors: ' . count($globalColors) . ', typography: ' . count($kitTypography));
        }

        // Source-site context for DYNAMIC widgets the JSON can't describe:
        // project grids and category tiles read the WP database directly.
        $context = ['projects' => [], 'categories' => []];
        $origin = rtrim((string) ($this->option('origin') ?: ''), '/');
        foreach ($wp->table("{$prefix}posts")->where('post_type', 'awaiken-project')->where('post_status', 'publish')
            ->orderByDesc('post_date')->limit(2)->get() as $proj) {
            $thumbId = $wp->table("{$prefix}postmeta")->where('post_id', $proj->ID)->where('meta_key', '_thumbnail_id')->value('meta_value');
            $file = $thumbId ? $wp->table("{$prefix}postmeta")->where('post_id', $thumbId)->where('meta_key', '_wp_attached_file')->value('meta_value') : null;
            $context['projects'][] = [
                'title' => $proj->post_title,
                'text' => mb_substr(trim(strip_tags($proj->post_content)), 0, 400),
                'image' => $file && $origin ? "{$origin}/wp-content/uploads/{$file}" : null,
            ];
        }
        // Category tiles: parsed off the source's own catalog page markup.
        if ((int) $this->option('catalog-post') > 0) {
            $html = (string) $wp->table("{$prefix}posts")->where('ID', (int) $this->option('catalog-post'))->value('post_content');
            preg_match_all('#<img src="([^"]+)" alt="([^"]*)"#', $html, $m, PREG_SET_ORDER);
            foreach (array_slice($m, 0, 12) as $tile) {
                $context['categories'][] = ['name' => html_entity_decode($tile[2]), 'image' => $tile[1]];
            }
        }
        $this->line('context: ' . count($context['projects']) . ' projects, ' . count($context['categories']) . ' categories');

        $pairs = [];
        foreach (array_filter(explode(',', (string) $this->option('pages'))) as $pair) {
            [$id, $slug] = array_pad(explode(':', trim($pair), 2), 2, null);
            if (is_numeric($id) && $slug) {
                $pairs[(int) $id] = trim($slug);
            }
        }
        if ($pairs === [] && (int) $this->option('posts') === 0) {
            $this->error('No --pages given (expected e.g. 4967:home,187:about-us).');

            return self::FAILURE;
        }

        // Media import with cross-page dedupe.
        $assetMap = [];
        $importImage = function (string $url, string $alt) use ($media, $site, &$assetMap): string {
            if (isset($assetMap[$url])) {
                return $assetMap[$url];
            }
            $asset = $media->fromUrl($site, $url, $alt);
            $serve = $asset ? "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve" : $url;

            return $assetMap[$url] = $serve;
        };

        $heroSlider = false;
        foreach ($pairs as $wpId => $slug) {
            $row = $wp->table("{$prefix}posts as p")
                ->join("{$prefix}postmeta as m", 'm.post_id', '=', 'p.ID')
                ->where('p.ID', $wpId)->where('m.meta_key', '_elementor_data')
                ->select('p.post_title', 'm.meta_value')->first();
            if (!$row || !is_array($doc = json_decode((string) $row->meta_value, true))) {
                $this->warn("WP {$wpId}: no Elementor data — skipped");
                continue;
            }

            $tree = $compiler->compile($doc, $importImage, $globalColors, $context);
            if ($tree === []) {
                $this->warn("WP {$wpId}: produced no blocks — skipped");
                continue;
            }
            if (!$heroSlider && isset($tree[0]) && $this->nodeHasSlider($tree[0])) {
                $heroSlider = true;
            }

            $page = Page::where('site_id', $site->id)->where('slug', $slug)->first();
            if (!$page) {
                $page = Page::create([
                    'site_id' => $site->id, 'title' => (string) $row->post_title,
                    'slug' => $slug, 'status' => 'draft',
                ]);
                $this->line("created page {$slug}");
            }
            $blocks->syncBlocks($page, $tree);
            if ($this->option('publish')) {
                $page->update(['status' => 'published', 'published_at' => now()]);
            }

            $sections = count($tree);
            $this->info("WP {$wpId} → /{$slug} ({$sections} sections)");
        }

        // Auto-recipe: kit typography → the site's type scale, plus the
        // transparent-overlay header when the home hero is a slider. Kept in
        // settings.custom_css between markers so re-import refreshes just this
        // block and never clobbers hand-written CSS.
        if ($pairs !== []) {
            $this->applyAutoRecipe($site, $kitTypography, $heroSlider);
        }

        // Latest source POSTS → real CMS posts (feeds the latestposts block).
        $postCount = (int) $this->option('posts');
        if ($postCount > 0) {
            $imported = 0;
            $q = $wp->table("{$prefix}posts as p")->where('p.post_type', 'post')->where('p.post_status', 'publish');
            if (($lang = (string) $this->option('posts-lang')) !== '') {
                $q->join("{$prefix}term_relationships as lr", 'lr.object_id', '=', 'p.ID')
                    ->join("{$prefix}term_taxonomy as ltt", function ($j) {
                        $j->on('ltt.term_taxonomy_id', '=', 'lr.term_taxonomy_id')->where('ltt.taxonomy', 'language');
                    })
                    ->join("{$prefix}terms as lt", 'lt.term_id', '=', 'ltt.term_id')->where('lt.slug', $lang);
            }
            $wpPosts = $q->select('p.*')->orderByDesc('p.post_date')->orderByDesc('p.ID')->limit($postCount * 2)->get();
            foreach ($wpPosts as $wpPost) {
                if ($imported >= $postCount) {
                    break;
                }
                $slug = \Illuminate\Support\Str::slug(mb_substr($wpPost->post_title, 0, 60)) ?: ('post-' . $wpPost->ID);
                $exclude = array_filter(array_map('intval', explode(',', (string) $this->option('posts-exclude'))));
                if (in_array((int) $wpPost->ID, $exclude, true)) {
                    continue;
                }
                if (\App\Models\Post::withTrashed()->where('site_id', $site->id)->where('slug', $slug)->exists()) {
                    $imported++;
                    continue;
                }
                $thumbId = $wp->table("{$prefix}postmeta")->where('post_id', $wpPost->ID)->where('meta_key', '_thumbnail_id')->value('meta_value');
                $file = $thumbId ? $wp->table("{$prefix}postmeta")->where('post_id', $thumbId)->where('meta_key', '_wp_attached_file')->value('meta_value') : null;
                $featured = $file && $origin ? $importImage("{$origin}/wp-content/uploads/{$file}", $wpPost->post_title) : null;
                $post = \App\Models\Post::create([
                    'site_id' => $site->id,
                    'title' => $wpPost->post_title,
                    'slug' => $slug,
                    'excerpt' => mb_substr(trim(strip_tags($wpPost->post_content)), 0, 200),
                    'featured_image' => $featured,
                    'status' => 'published',
                    'published_at' => $wpPost->post_date,
                ]);
                $paras = array_slice(array_filter(array_map('trim', preg_split('#</p>|\n{2,}#', strip_tags($wpPost->post_content, '<p>')))), 0, 12);
                $tree = [[
                    'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'section', 'level' => 'section', 'order' => 0,
                    'data' => ['padding_top' => '48px', 'padding_bottom' => '48px', 'max_width' => '900px'],
                    'children' => [[
                        'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'row', 'level' => 'row', 'order' => 0,
                        'data' => ['layout' => '1', 'gap' => '24px'],
                        'children' => [[
                            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'column', 'level' => 'column', 'order' => 0,
                            'data' => [],
                            'children' => array_values(array_map(fn ($t, $i) => [
                                'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'text', 'level' => 'module', 'order' => $i,
                                'data' => ['content' => '<p>' . e(strip_tags($t)) . '</p>'],
                                'children' => [],
                            ], $paras, array_keys($paras))),
                        ]],
                    ]],
                ]];
                $blocks->syncBlocks($post, $tree);
                $imported++;
                $this->line("post: {$wpPost->post_title}");
            }
        }

        $this->info('Imported ' . count($assetMap) . ' media file(s).');

        return self::SUCCESS;
    }

    /** Does a compiled section node contain a slider anywhere below it? */
    private function nodeHasSlider(array $node): bool
    {
        if (($node['type'] ?? '') === 'slider') {
            return true;
        }
        foreach ($node['children'] ?? [] as $child) {
            if ($this->nodeHasSlider($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write the kit-derived type scale (+ overlay-header recipe when the hero
     * is a slider) into settings.custom_css, between markers, so re-import
     * refreshes only this block and leaves any hand-written CSS intact.
     */
    private function applyAutoRecipe(Site $site, array $kitTypography, bool $heroSlider): void
    {
        $rem = fn ($px) => rtrim(rtrim(number_format((float) $px / 16, 4, '.', ''), '0'), '.') . 'rem';
        $size = fn (string $id) => is_numeric($kitTypography[$id]['typography_font_size']['size'] ?? null)
            ? (float) $kitTypography[$id]['typography_font_size']['size'] : null;

        $vars = [];
        if (($primary = $size('primary')) !== null) {
            $vars[] = '--font-size-2xl:' . $rem($primary);
            $vars[] = '--font-size-3xl:' . $rem($primary * 1.4);
            if (is_numeric($lh = $kitTypography['primary']['typography_line_height']['size'] ?? null)) {
                $vars[] = '--line-height-heading:' . $lh;
                $vars[] = '--line-height-tight:' . $lh;
            }
        }
        if (($secondary = $size('secondary')) !== null) {
            $vars[] = '--font-size-xl:' . $rem($secondary);
        }

        $recipe = '';
        if ($vars !== []) {
            $recipe .= ":root{" . implode(';', $vars) . "}\n";
        }
        if ($heroSlider) {
            $recipe .= <<<'CSS'
                body:has(.pos-main > section:first-child .sp-slider) .site-grid{position:relative}
                body:has(.pos-main > section:first-child .sp-slider) .pos-nav{position:absolute;top:0;left:0;right:0;z-index:1000}
                body:has(.pos-main > section:first-child .sp-slider) .pos-nav .site-nav{position:static!important;background:transparent!important;border-bottom:none!important;box-shadow:none!important;backdrop-filter:none!important}
                body:has(.pos-main > section:first-child .sp-slider) .pos-nav .menu-top-link,body:has(.pos-main > section:first-child .sp-slider) .pos-nav .menu-custom-link{color:#fff!important}
                .sp-slider .sp-slide .sp-layer:has(h1){width:60%!important}

                CSS;
        }
        if (trim($recipe) === '') {
            return;
        }

        $begin = '/* >>> elementor auto-recipe */';
        $end = '/* <<< elementor auto-recipe */';
        $block = $begin . "\n" . rtrim($recipe) . "\n" . $end;

        $existing = (string) ($site->settings['custom_css'] ?? '');
        // Replace a previous auto-recipe block; otherwise append.
        $pattern = '#' . preg_quote($begin, '#') . '.*?' . preg_quote($end, '#') . '#s';
        $merged = preg_match($pattern, $existing) === 1
            ? preg_replace($pattern, $block, $existing)
            : trim($existing . "\n\n" . $block);

        $site->update(['settings' => array_merge($site->settings ?? [], ['custom_css' => $merged])]);
        $this->line('auto-recipe: type scale' . ($heroSlider ? ' + overlay header' : '') . ' → custom_css');
    }
}
