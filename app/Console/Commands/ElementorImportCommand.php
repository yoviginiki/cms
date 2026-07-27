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
        $globalColors = [];
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
            $this->line('kit colors: ' . count($globalColors));
        }

        $pairs = [];
        foreach (array_filter(explode(',', (string) $this->option('pages'))) as $pair) {
            [$id, $slug] = array_pad(explode(':', trim($pair), 2), 2, null);
            if (is_numeric($id) && $slug) {
                $pairs[(int) $id] = trim($slug);
            }
        }
        if ($pairs === []) {
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

        foreach ($pairs as $wpId => $slug) {
            $row = $wp->table("{$prefix}posts as p")
                ->join("{$prefix}postmeta as m", 'm.post_id', '=', 'p.ID')
                ->where('p.ID', $wpId)->where('m.meta_key', '_elementor_data')
                ->select('p.post_title', 'm.meta_value')->first();
            if (!$row || !is_array($doc = json_decode((string) $row->meta_value, true))) {
                $this->warn("WP {$wpId}: no Elementor data — skipped");
                continue;
            }

            $tree = $compiler->compile($doc, $importImage, $globalColors);
            if ($tree === []) {
                $this->warn("WP {$wpId}: produced no blocks — skipped");
                continue;
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

        $this->info('Imported ' . count($assetMap) . ' media file(s).');

        return self::SUCCESS;
    }
}
