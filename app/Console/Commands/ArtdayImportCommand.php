<?php

namespace App\Console\Commands;

use App\Domain\Assets\Services\AssetService;
use App\Domain\Blocks\Services\BlockService;
use App\Domain\Categories\Services\CategoryService;
use App\Domain\Import\Services\HtmlBlockSplitter;
use App\Domain\Posts\Services\PostService;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Migrates the artday.bg WordPress site (classic-editor news/magazine, tagDiv
 * Newspaper theme) DIRECTLY from its local MySQL DB into the CMS "artday" site
 * as native posts/categories/blocks + locally-registered media assets.
 *
 * Built for scale + safety: reads from the WP DB (no WXR round-trip), registers
 * the 8k local upload originals without HTTP, splits classic HTML into native
 * blocks (HtmlBlockSplitter), preserves WP slugs verbatim for URL parity, and
 * is fully resumable/idempotent via a JSON state file (re-runs skip done work).
 *
 *   php artisan artday:import --wp-pass=SECRET               # everything
 *   php artisan artday:import --wp-pass=SECRET --only-cat=news --limit=50  # pilot
 */
class ArtdayImportCommand extends Command
{
    protected $signature = 'artday:import
        {--site=artday : Target CMS site slug}
        {--tenant=019dfba5-a96b-719d-954d-60a4a549f949 : Ensodo tenant UUID (RLS)}
        {--owner=019dfba5-aaf5-71c4-8ff8-3b53e1ff9dee : CMS user id to own imported posts}
        {--wp-db=cytechno_artday}
        {--wp-user=cytechno_artday}
        {--wp-pass= : WP DB password (required)}
        {--wp-host=127.0.0.1}
        {--wp-prefix=wpxv_}
        {--uploads=/home/cytechno/web/artday.bg/public_html/wp-content/uploads}
        {--only-cat= : Import only posts in this WP category slug (pilot)}
        {--limit=0 : Max posts to import this run (0 = all)}
        {--skip-categories} {--skip-media} {--skip-posts}
        {--views-only : Only backfill seo_meta.hist_views on existing posts from WP post_views_count}
        {--fresh : Ignore the saved state file}';

    protected $description = 'Import artday.bg WordPress content directly from its DB into the CMS artday site';

    private string $prefix;
    private string $uploads;
    private array $state;
    private string $stateFile;

    public function handle(
        CategoryService $categories,
        AssetService $assets,
        PostService $posts,
        BlockService $blocks,
        HtmlBlockSplitter $splitter,
    ): int {
        $pass = (string) $this->option('wp-pass');
        if ($pass === '') {
            $this->error('--wp-pass is required.');
            return self::FAILURE;
        }
        $this->prefix = (string) $this->option('wp-prefix');
        $this->uploads = rtrim((string) $this->option('uploads'), '/');

        // RLS for all CMS writes.
        DB::statement("SET app.current_tenant_id = '" . $this->option('tenant') . "'");
        $site = Site::where('slug', $this->option('site'))->first();
        if (!$site) {
            $this->error("Site '{$this->option('site')}' not found.");
            return self::FAILURE;
        }

        // Runtime WP connection (no permanent config change).
        config(['database.connections.artday_wp' => [
            'driver' => 'mysql', 'host' => $this->option('wp-host'), 'port' => 3306,
            'database' => $this->option('wp-db'), 'username' => $this->option('wp-user'),
            'password' => $pass, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => false,
        ]]);
        $wp = DB::connection('artday_wp');

        $this->stateFile = storage_path('app/artday-import/state.json');
        File::ensureDirectoryExists(dirname($this->stateFile));
        $this->state = (!$this->option('fresh') && File::exists($this->stateFile))
            ? json_decode(File::get($this->stateFile), true) ?: []
            : [];
        $this->state += ['cat_map' => [], 'asset_map' => [], 'basename_map' => [], 'done_posts' => []];

        // Backfill-only: apply WP all-time view counts to already-imported posts
        // (seeds the "most read" baseline without re-importing content).
        if ($this->option('views-only')) {
            $this->backfillViews($wp, $site);
            return self::SUCCESS;
        }

        if (!$this->option('skip-categories')) {
            $this->importCategories($wp, $site, $categories);
        }
        if (!$this->option('skip-media')) {
            $this->importMedia($wp, $site, $assets);
        }
        if (!$this->option('skip-posts')) {
            $this->importPosts($wp, $site, $posts, $blocks, $splitter);
        }

        $this->save();
        $this->info('Done. cats=' . count($this->state['cat_map'])
            . ' assets=' . count($this->state['asset_map'])
            . ' posts=' . count($this->state['done_posts']));
        return self::SUCCESS;
    }

    private function importCategories($wp, Site $site, CategoryService $svc): void
    {
        $rows = $wp->select("
            SELECT t.term_id, t.name, t.slug, tt.parent, tt.description
            FROM {$this->prefix}terms t
            JOIN {$this->prefix}term_taxonomy tt ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'category' ORDER BY tt.parent ASC, t.term_id ASC");
        $this->info('Categories: ' . count($rows));

        // Two passes so parents exist before children.
        $byWpId = [];
        foreach ($rows as $r) {
            $byWpId[$r->term_id] = $r;
        }
        foreach ($byWpId as $r) {
            if (isset($this->state['cat_map'][$r->term_id])) {
                continue;
            }
            // Reuse an existing same-slug category (idempotent re-runs).
            $existing = Category::where('site_id', $site->id)->where('slug', $r->slug)->first();
            if ($existing) {
                $this->state['cat_map'][$r->term_id] = $existing->id;
                continue;
            }
            $parentId = ($r->parent && isset($this->state['cat_map'][$r->parent]))
                ? $this->state['cat_map'][$r->parent] : null;
            $cat = $svc->createCategory([
                'name' => $r->name,
                'slug' => $r->slug,            // verbatim — CategoryService keeps a provided slug
                'description' => $r->description ?: null,
                'parent_id' => $parentId,
                'is_public' => true,
            ], $site);
            $this->state['cat_map'][$r->term_id] = $cat->id;
        }
        $this->save();
    }

    private function importMedia($wp, Site $site, AssetService $svc): void
    {
        // When piloting one category, scope media to that category's posts'
        // featured images + inline-image attachments so a pilot doesn't process
        // all ~8k uploads. Full runs (no --only-cat) import everything.
        $only = (string) $this->option('only-cat');
        $scope = '';
        $bindings = [];
        if ($only !== '') {
            $scope = "AND p.ID IN (
                SELECT th.meta_value FROM {$this->prefix}postmeta th
                JOIN {$this->prefix}term_relationships tr ON tr.object_id = th.post_id
                JOIN {$this->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy='category'
                JOIN {$this->prefix}terms t ON t.term_id = tt.term_id
                WHERE th.meta_key = '_thumbnail_id' AND t.slug = ?)";
            $bindings[] = $only;
        }
        $rows = $wp->select("
            SELECT p.ID, f.meta_value AS file, a.meta_value AS alt
            FROM {$this->prefix}posts p
            JOIN {$this->prefix}postmeta f ON f.post_id = p.ID AND f.meta_key = '_wp_attached_file'
            LEFT JOIN {$this->prefix}postmeta a ON a.post_id = p.ID AND a.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' {$scope}", $bindings);
        $total = count($rows);
        $this->info("Media: {$total} attachments");
        $bar = $this->output->createProgressBar($total);
        $done = 0;
        foreach ($rows as $r) {
            $bar->advance();
            if (isset($this->state['asset_map'][$r->ID])) {
                continue;
            }
            $path = $this->uploads . '/' . ltrim($r->file, '/');
            if (!is_file($path)) {
                continue;
            }
            try {
                $mime = mime_content_type($path) ?: 'application/octet-stream';
                $file = new UploadedFile($path, basename($path), $mime, null, true);
                $asset = $svc->upload($site, $file);
                if ($r->alt && !$asset->alt_text) {
                    $asset->update(['alt_text' => $r->alt]);
                }
                $dims = $asset->dimensions ?? [];
                $this->state['asset_map'][$r->ID] = $asset->id;
                $this->state['basename_map'][strtolower(basename($r->file))] = [
                    'id' => $asset->id, 'site_id' => $site->id,
                    'w' => $dims['width'] ?? null, 'h' => $dims['height'] ?? null,
                    'alt' => $r->alt ?: '',
                ];
            } catch (\Throwable $e) {
                $this->warn("  media {$r->ID} ({$r->file}): {$e->getMessage()}");
            }
            if (++$done % 100 === 0) {
                $this->save();
            }
        }
        $bar->finish();
        $this->newLine();
        $this->save();
    }

    private function importPosts($wp, Site $site, PostService $svc, BlockService $blocks, HtmlBlockSplitter $splitter): void
    {
        $only = (string) $this->option('only-cat');
        $limit = (int) $this->option('limit');
        $catFilter = '';
        $bindings = [];
        if ($only !== '') {
            $catFilter = "AND p.ID IN (
                SELECT tr.object_id FROM {$this->prefix}term_relationships tr
                JOIN {$this->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$this->prefix}terms t ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'category' AND t.slug = ?)";
            $bindings[] = $only;
        }
        $rows = $wp->select("
            SELECT p.ID, p.post_title, p.post_name, p.post_content, p.post_excerpt, p.post_date,
                   c.slug AS cat_slug, th.meta_value AS thumb_wp_id, md.meta_value AS metadesc,
                   pv.meta_value AS views
            FROM {$this->prefix}posts p
            LEFT JOIN {$this->prefix}term_relationships tr ON tr.object_id = p.ID
            LEFT JOIN {$this->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
            LEFT JOIN {$this->prefix}terms c ON c.term_id = tt.term_id
            LEFT JOIN {$this->prefix}postmeta th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
            LEFT JOIN {$this->prefix}postmeta md ON md.post_id = p.ID AND md.meta_key = '_yoast_wpseo_metadesc'
            LEFT JOIN {$this->prefix}postmeta pv ON pv.post_id = p.ID AND pv.meta_key = 'post_views_count'
            WHERE p.post_type = 'post' AND p.post_status = 'publish' {$catFilter}
            GROUP BY p.ID
            ORDER BY p.post_date DESC", $bindings);

        $total = count($rows);
        $this->info("Posts: {$total}" . ($only ? " (category '{$only}')" : ''));
        $bar = $this->output->createProgressBar($total);
        $count = 0;
        foreach ($rows as $r) {
            $bar->advance();
            if (isset($this->state['done_posts'][$r->ID])) {
                continue;
            }
            // Idempotent across state resets: an existing same-slug post means it
            // was imported before — record and skip (never create a -1 duplicate).
            if ($r->post_name && ($existing = Post::where('site_id', $site->id)->where('slug', $r->post_name)->first())) {
                $this->state['done_posts'][$r->ID] = $existing->id;
                continue;
            }
            if ($limit > 0 && $count >= $limit) {
                break;
            }
            try {
                $categoryId = ($r->cat_slug && isset($this->state['cat_map']))
                    ? $this->catIdBySlug($site, $r->cat_slug) : null;
                // featured_image must be a serve URL (the publisher rewrites it to
                // a static/webp path) — NOT a bare asset id, or listing cards and
                // og:image get a broken src.
                $featuredId = ($r->thumb_wp_id && isset($this->state['asset_map'][$r->thumb_wp_id]))
                    ? $this->state['asset_map'][$r->thumb_wp_id] : null;
                $featured = $featuredId ? "/api/v1/sites/{$site->id}/assets/{$featuredId}/serve" : null;

                $post = $svc->createPost([
                    'title' => $r->post_title ?: '(untitled)',
                    'slug' => $r->post_name ?: null,
                    'excerpt' => $r->post_excerpt ?: null,
                    'status' => 'published',
                    'category_id' => $categoryId,
                    'featured_image' => $featured,
                    'author_id' => $this->option('owner'),
                    'published_at' => $r->post_date ?: now(),
                    'seo_meta' => array_filter([
                        'description' => $r->metadesc ?: null,
                        // Historical all-time views → baseline "most read" ranking
                        // until the live daily refresh takes over (win_views).
                        'hist_views' => ($r->views !== null && $r->views !== '') ? (int) $r->views : null,
                    ], fn ($v) => $v !== null),
                ], $site);

                // Enforce verbatim slug (PostService slugifies; artday slugs are
                // ASCII so this is usually a no-op, but URL parity is a hard req).
                if ($r->post_name && $post->slug !== $r->post_name) {
                    Post::where('id', $post->id)->update(['slug' => $r->post_name]);
                }

                $tree = $splitter->split((string) $r->post_content, $this->state['basename_map'], $site->id);

                // Prepend the featured image as an asset-backed hero block so it
                // renders as an optimized <picture> (WebP + dimensions) at the
                // top of the article — the tagDiv source shows it the same way,
                // and it becomes the post's LCP element. featured_image stays on
                // the post too (og:image, cards, archive thumbnails).
                if ($featured && ($hero = $this->featuredBlock($featured, $site->id, (string) $r->post_title))) {
                    array_unshift($tree, $hero);
                    foreach ($tree as $i => &$b) {
                        $b['order'] = $i;
                    }
                    unset($b);
                }

                if (!empty($tree)) {
                    $blocks->syncBlocks($post, $tree);
                }

                $this->state['done_posts'][$r->ID] = $post->id;
                $count++;
            } catch (\Throwable $e) {
                $this->warn("  post {$r->ID} ({$r->post_name}): {$e->getMessage()}");
            }
            if ($count % 50 === 0) {
                $this->save();
            }
        }
        $bar->finish();
        $this->newLine();
        $this->save();
    }

    /** Apply WP all-time view counts to existing posts as the "most read" baseline. */
    private function backfillViews($wp, Site $site): void
    {
        $rows = $wp->select("
            SELECT p.post_name, pv.meta_value AS views
            FROM {$this->prefix}posts p
            JOIN {$this->prefix}postmeta pv ON pv.post_id = p.ID AND pv.meta_key = 'post_views_count'
            WHERE p.post_type = 'post' AND p.post_status = 'publish' AND pv.meta_value <> ''");
        $total = count($rows);
        $this->info("Backfilling views onto up to {$total} posts");
        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        foreach ($rows as $r) {
            $bar->advance();
            if (!$r->post_name) {
                continue;
            }
            $post = Post::where('site_id', $site->id)->where('slug', $r->post_name)->first();
            if (!$post) {
                continue;
            }
            $sm = $post->seo_meta ?? [];
            $sm['hist_views'] = (int) $r->views;
            $post->update(['seo_meta' => $sm]);
            $updated++;
        }
        $bar->finish();
        $this->newLine();
        $this->info("views backfilled: {$updated}");
    }

    /** Build an asset-backed hero image block for a post's featured image. */
    private function featuredBlock(string $assetId, string $siteId, string $titleFallback): ?array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return null;
        }
        $dims = $asset->dimensions ?? [];
        $data = [
            'asset_id' => $asset->id,
            'url' => "/api/v1/sites/{$siteId}/assets/{$asset->id}/serve",
            'alt' => $asset->alt_text ?: $titleFallback,
            'caption' => '',
        ];
        if (!empty($dims['width'])) $data['width'] = (string) $dims['width'];
        if (!empty($dims['height'])) $data['height'] = (string) $dims['height'];

        return ['type' => 'image', 'data' => $data, 'children' => [], 'order' => 0, 'id' => Str::uuid()->toString()];
    }

    private function catIdBySlug(Site $site, string $slug): ?string
    {
        static $cache = [];
        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }
        $id = Category::where('site_id', $site->id)->where('slug', $slug)->value('id');
        return $cache[$slug] = $id;
    }

    private function save(): void
    {
        File::put($this->stateFile, json_encode($this->state, JSON_UNESCAPED_UNICODE));
    }
}
