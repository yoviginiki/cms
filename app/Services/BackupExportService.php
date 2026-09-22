<?php

namespace App\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Sites\Support\SiteSecrets;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;

/**
 * Site backup (F19, audit 2026-09-22).
 *
 * Schema 2.0.0. The export carries the block TREE (not a flat list with a
 * misspelled parent column), raw HTML, editor mode, publish dates, the theme
 * DOCUMENT, categories, menus with items, redirects with their kind, and the
 * section templates. `scope` states explicitly what is and is not included
 * (assets ship as metadata + checksums, not bytes; collections, magazines,
 * grids, sliders, global sections and users are NOT part of this backup).
 * `restore()` writes into an EMPTY target site in one transaction and
 * reports what it created. Secrets are never exported.
 */
class BackupExportService
{
    public const SCHEMA_VERSION = '2.0.0';

    public const INCLUDED = ['site settings (secrets stripped)', 'seo defaults', 'theme (document/config)', 'categories',
        'pages (tree, raw_html, editor_mode, status, dates, seo_meta)', 'posts (tree, status, dates, seo_meta, category, tags)',
        'menus + items', 'redirects', 'section templates', 'asset metadata + checksums'];

    public const EXCLUDED = ['asset files (bytes)', 'collections/records', 'magazines/issues', 'grids', 'sliders',
        'global sections', 'forms/submissions', 'users', 'deployments/versions', 'translations'];

    public function __construct(
        private ActivityLogService $activityLog,
        private BlockService $blocks,
    ) {}

    /**
     * Export a site backup as a JSON-serializable manifest.
     */
    public function export(Site $site): array
    {
        $site->load(['theme', 'categories', 'menus.items', 'assets']);
        $pages = Page::where('site_id', $site->id)->withTrashed()->orderBy('sort_order')->get();
        $posts = Post::with(['tags', 'category'])->where('site_id', $site->id)->orderBy('created_at')->get();

        $pageSlugById = $pages->pluck('slug', 'id')->all();
        $catSlugById = $site->categories->pluck('slug', 'id')->all();
        $postSlugById = $posts->pluck('slug', 'id')->all();

        $manifest = [
            'schema_version' => self::SCHEMA_VERSION,
            'cms_version' => (string) config('cms.version', '1.0.0'),
            'exported_at' => now()->toIso8601String(),
            'scope' => ['included' => self::INCLUDED, 'excluded' => self::EXCLUDED],
            'site' => [
                'name' => $site->name,
                'slug' => $site->slug,
                'status' => $site->status,
                'custom_domain' => $site->custom_domain,
                'settings' => SiteSecrets::strip($site->settings ?? []),
                'seo_defaults' => $site->seo_defaults ?? [],
            ],
            'theme' => $site->theme ? [
                'name' => $site->theme->name,
                'slug' => $site->theme->slug,
                'version' => $site->theme->version,
                'is_system' => (bool) $site->theme->is_system,
                'config' => $site->theme->config,
                'document' => $site->theme->document,
                'modes' => $site->theme->modes,
                'schema_version' => $site->theme->schema_version,
            ] : null,
            'categories' => $site->categories->map(fn ($c) => [
                'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description,
                'parent_slug' => $c->parent_id ? ($catSlugById[$c->parent_id] ?? null) : null,
                'sort_order' => $c->sort_order,
            ])->values()->all(),
            'pages' => $pages->map(fn ($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'parent_slug' => $p->parent_id ? ($pageSlugById[$p->parent_id] ?? null) : null,
                'status' => $p->status,
                'editor_mode' => $p->editor_mode,
                'experience_mode' => $p->experience_mode,
                'raw_html' => $p->raw_html,
                'seo_meta' => $p->seo_meta,
                'sort_order' => $p->sort_order,
                'published_at' => $p->published_at?->toIso8601String(),
                'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                'blocks' => $this->blocks->getBlockTree($p),
                'deleted_at' => $p->deleted_at?->toIso8601String(),
            ])->values()->all(),
            'posts' => $posts->map(fn ($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'status' => $p->status,
                'editor_mode' => $p->editor_mode,
                'experience_mode' => $p->experience_mode,
                'featured_image' => $p->featured_image,
                'video_url' => $p->video_url,
                'thumbnail' => $p->thumbnail,
                'post_format' => $p->post_format,
                'seo_meta' => $p->seo_meta,
                'category_slug' => $p->category?->slug,
                'tags' => $p->tags->pluck('name')->all(),
                'published_at' => $p->published_at?->toIso8601String(),
                'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                'blocks' => $this->blocks->getBlockTree($p),
            ])->values()->all(),
            'menus' => $site->menus->map(fn ($m) => [
                'name' => $m->name, 'slug' => $m->slug, 'location' => $m->location, 'locale' => $m->locale, 'style' => $m->style,
                'items' => $this->menuItems($m, null, $pageSlugById, $postSlugById, $catSlugById),
            ])->values()->all(),
            'redirects' => Redirect::where('site_id', $site->id)->get()->map(fn ($r) => [
                'source_path' => $r->source_path, 'target_url' => $r->target_url,
                'status_code' => (int) $r->status_code, 'is_regex' => (bool) $r->is_regex,
            ])->values()->all(),
            'section_templates' => \App\Models\BlockTemplate::where('site_id', $site->id)->get()->map(fn ($t) => [
                'name' => $t->name, 'category' => $t->category, 'blocks_data' => $t->blocks_data,
            ])->values()->all(),
            'assets' => $site->assets->map(fn ($a) => [
                'original_name' => $a->original_name, 'mime_type' => $a->mime_type,
                'checksum' => $a->checksum, 'file_size' => $a->file_size, 'alt_text' => $a->alt_text,
            ])->values()->all(),
            'stats' => [
                'pages_count' => $pages->count(),
                'posts_count' => $posts->count(),
            ],
        ];

        $this->activityLog->log('backup.exported', $site->id, 'site', $site->id, [
            'pages_count' => $manifest['stats']['pages_count'],
            'posts_count' => $manifest['stats']['posts_count'],
        ]);

        return $manifest;
    }

    /** Nested menu items for a parent (null = roots). */
    private function menuItems(Menu $menu, ?string $parentId, array $pages, array $posts, array $cats): array
    {
        return $menu->items->where('parent_id', $parentId)->sortBy('sort_order')->values()->map(fn (MenuItem $i) => [
            'label' => $i->label, 'url' => $i->url, 'target' => $i->target, 'css_class' => $i->css_class,
            'icon' => $i->icon, 'sort_order' => $i->sort_order,
            'page_slug' => $i->page_id ? ($pages[$i->page_id] ?? null) : null,
            'post_slug' => $i->post_id ? ($posts[$i->post_id] ?? null) : null,
            'category_slug' => $i->category_id ? ($cats[$i->category_id] ?? null) : null,
            'children' => $this->menuItems($menu, $i->id, $pages, $posts, $cats),
        ])->all();
    }

    /**
     * Dry-run validation of a manifest. Structural only — `can_restore`
     * means the manifest is well-formed for restore(), not that a restore
     * was performed.
     */
    public function validateForRestore(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        if (empty($manifest['schema_version'])) $errors[] = 'Missing schema_version';
        if (empty($manifest['exported_at'])) $errors[] = 'Missing exported_at';
        if (empty($manifest['site']['name'])) $errors[] = 'Missing site name';
        if (empty($manifest['site']['slug'])) $errors[] = 'Missing site slug';

        $supported = ['1.0.0', self::SCHEMA_VERSION];
        if (!empty($manifest['schema_version']) && !in_array($manifest['schema_version'], $supported, true)) {
            $errors[] = "Unsupported schema version {$manifest['schema_version']}";
        }
        if (($manifest['schema_version'] ?? null) === '1.0.0') {
            $warnings[] = 'Schema 1.0.0 exports carry a flat block list without ids; block nesting cannot be restored.';
        }
        if (SiteSecrets::strip($manifest['site']['settings'] ?? []) !== ($manifest['site']['settings'] ?? [])) {
            $errors[] = 'Manifest contains secrets';
        }
        foreach (['pages', 'posts', 'menus', 'redirects', 'categories'] as $k) {
            if (isset($manifest[$k]) && !is_array($manifest[$k])) {
                $errors[] = "{$k} must be a list";
            }
        }
        foreach ($manifest['pages'] ?? [] as $i => $page) {
            if (empty($page['title']) && empty($page['slug'])) {
                $warnings[] = "Page {$i}: missing title and slug";
            }
        }

        return [
            'can_restore' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                'pages' => count($manifest['pages'] ?? []),
                'posts' => count($manifest['posts'] ?? []),
                'menus' => count($manifest['menus'] ?? []),
                'redirects' => count($manifest['redirects'] ?? []),
            ],
        ];
    }

    /**
     * Restore a 2.0.0 manifest into an EMPTY target site (no pages/posts).
     * One transaction: either everything lands or nothing does.
     *
     * @return array{pages:int,posts:int,categories:int,menus:int,redirects:int,section_templates:int,theme:bool}
     */
    public function restore(array $manifest, Site $target): array
    {
        $check = $this->validateForRestore($manifest);
        if (!$check['can_restore']) {
            throw new \InvalidArgumentException('Manifest cannot be restored: ' . implode('; ', $check['errors']));
        }
        if (($manifest['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException('Only schema ' . self::SCHEMA_VERSION . ' manifests can be restored.');
        }
        if (Page::where('site_id', $target->id)->withTrashed()->exists() || Post::where('site_id', $target->id)->exists()) {
            throw new \InvalidArgumentException('Restore target must be an empty site (no pages or posts).');
        }

        return DB::transaction(function () use ($manifest, $target) {
            $result = ['pages' => 0, 'posts' => 0, 'categories' => 0, 'menus' => 0, 'redirects' => 0, 'section_templates' => 0, 'theme' => false];

            $target->update([
                'settings' => array_merge($target->settings ?? [], SiteSecrets::strip($manifest['site']['settings'] ?? [])),
                'seo_defaults' => $manifest['site']['seo_defaults'] ?? [],
            ]);

            if (!empty($manifest['theme']) && empty($manifest['theme']['is_system'])) {
                $theme = Theme::create([
                    'site_id' => $target->id,
                    'name' => $manifest['theme']['name'] ?? 'Restored',
                    'slug' => $manifest['theme']['slug'] ?? 'restored',
                    'version' => $manifest['theme']['version'] ?? '1.0.0',
                    'config' => $manifest['theme']['config'] ?? [],
                    'manifest_json' => [],
                    'template_path' => 'themes/default',
                    'document' => $manifest['theme']['document'] ?? [],
                    'modes' => $manifest['theme']['modes'] ?? ['light'],
                    'schema_version' => $manifest['theme']['schema_version'] ?? '1.0.0',
                ]);
                $target->update(['active_theme_id' => $theme->id]);
                $result['theme'] = true;
            }

            $catIds = [];
            foreach ($manifest['categories'] ?? [] as $c) {
                $cat = Category::firstOrCreate(['site_id' => $target->id, 'slug' => $c['slug']], [
                    'name' => $c['name'] ?? $c['slug'], 'description' => $c['description'] ?? null, 'sort_order' => $c['sort_order'] ?? 0,
                ]);
                $catIds[$c['slug']] = $cat->id;
                $result['categories']++;
            }
            foreach ($manifest['categories'] ?? [] as $c) {
                if (!empty($c['parent_slug']) && isset($catIds[$c['parent_slug']], $catIds[$c['slug']])) {
                    Category::whereKey($catIds[$c['slug']])->update(['parent_id' => $catIds[$c['parent_slug']]]);
                }
            }

            $pageIds = [];
            $pages = collect($manifest['pages'] ?? [])->sortBy(fn ($p) => empty($p['parent_slug']) ? 0 : 1);
            foreach ($pages as $p) {
                $page = Page::create([
                    'site_id' => $target->id,
                    'title' => $p['title'] ?? $p['slug'],
                    'slug' => $p['slug'],
                    'parent_id' => !empty($p['parent_slug']) ? ($pageIds[$p['parent_slug']] ?? null) : null,
                    'status' => $p['status'] ?? 'draft',
                    'editor_mode' => $p['editor_mode'] ?? 'block',
                    'experience_mode' => $p['experience_mode'] ?? 'standard',
                    'raw_html' => $p['raw_html'] ?? null,
                    'seo_meta' => $p['seo_meta'] ?? [],
                    'sort_order' => $p['sort_order'] ?? 0,
                    'published_at' => $p['published_at'] ?? null,
                    'scheduled_at' => $p['scheduled_at'] ?? null,
                ]);
                if (!empty($p['deleted_at'])) {
                    $page->delete();
                }
                $pageIds[$p['slug']] = $page->id;
                if (!empty($p['blocks'])) {
                    $this->blocks->syncTrusted($page, $this->stripIds($p['blocks']));
                }
                $result['pages']++;
            }

            $postIds = [];
            foreach ($manifest['posts'] ?? [] as $p) {
                $post = Post::create([
                    'site_id' => $target->id,
                    'title' => $p['title'] ?? $p['slug'],
                    'slug' => $p['slug'],
                    'excerpt' => $p['excerpt'] ?? null,
                    'status' => $p['status'] ?? 'draft',
                    'editor_mode' => $p['editor_mode'] ?? 'block',
                    'experience_mode' => $p['experience_mode'] ?? 'standard',
                    'featured_image' => $p['featured_image'] ?? null,
                    'video_url' => $p['video_url'] ?? null,
                    'thumbnail' => $p['thumbnail'] ?? null,
                    'post_format' => $p['post_format'] ?? 'standard',
                    'seo_meta' => $p['seo_meta'] ?? [],
                    'category_id' => !empty($p['category_slug']) ? ($catIds[$p['category_slug']] ?? null) : null,
                    'published_at' => $p['published_at'] ?? null,
                    'scheduled_at' => $p['scheduled_at'] ?? null,
                ]);
                $postIds[$p['slug']] = $post->id;
                if (!empty($p['tags']) && method_exists($post, 'tags')) {
                    $tagIds = [];
                    foreach ($p['tags'] as $name) {
                        $tag = \App\Models\Tag::firstOrCreate(['site_id' => $target->id, 'slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name]);
                        $tagIds[] = $tag->id;
                    }
                    $post->tags()->sync($tagIds);
                }
                if (!empty($p['blocks'])) {
                    $this->blocks->syncTrusted($post, $this->stripIds($p['blocks']));
                }
                $result['posts']++;
            }

            foreach ($manifest['menus'] ?? [] as $m) {
                $menu = Menu::create([
                    'site_id' => $target->id, 'name' => $m['name'] ?? $m['slug'], 'slug' => $m['slug'],
                    'location' => $m['location'] ?? null, 'locale' => $m['locale'] ?? null, 'style' => $m['style'] ?? null,
                ]);
                $this->restoreMenuItems($menu, $m['items'] ?? [], null, $pageIds, $postIds, $catIds);
                $result['menus']++;
            }

            foreach ($manifest['redirects'] ?? [] as $r) {
                Redirect::create([
                    'site_id' => $target->id, 'source_path' => $r['source_path'], 'target_url' => $r['target_url'],
                    'status_code' => (int) ($r['status_code'] ?? 301), 'is_regex' => (bool) ($r['is_regex'] ?? false),
                ]);
                $result['redirects']++;
            }

            foreach ($manifest['section_templates'] ?? [] as $t) {
                \App\Models\BlockTemplate::create([
                    'site_id' => $target->id, 'name' => $t['name'] ?? 'Section',
                    'slug' => \Illuminate\Support\Str::slug(($t['name'] ?? 'section') . '-' . \Illuminate\Support\Str::random(6)),
                    'category' => $t['category'] ?? null,
                    'blocks_data' => $t['blocks_data'] ?? [],
                ]);
                $result['section_templates']++;
            }

            $this->activityLog->log('backup.restored', $target->id, 'site', $target->id, $result);

            return $result;
        });
    }

    private function restoreMenuItems(Menu $menu, array $items, ?string $parentId, array $pages, array $posts, array $cats): void
    {
        foreach ($items as $i) {
            $item = MenuItem::create([
                'menu_id' => $menu->id, 'parent_id' => $parentId,
                'label' => $i['label'] ?? '', 'url' => $i['url'] ?? null, 'target' => $i['target'] ?? null,
                'css_class' => $i['css_class'] ?? null, 'icon' => $i['icon'] ?? null, 'sort_order' => $i['sort_order'] ?? 0,
                'page_id' => !empty($i['page_slug']) ? ($pages[$i['page_slug']] ?? null) : null,
                'post_id' => !empty($i['post_slug']) ? ($posts[$i['post_slug']] ?? null) : null,
                'category_id' => !empty($i['category_slug']) ? ($cats[$i['category_slug']] ?? null) : null,
            ]);
            $this->restoreMenuItems($menu, $i['children'] ?? [], $item->id, $pages, $posts, $cats);
        }
    }

    private function stripIds(array $blocks): array
    {
        return array_map(function ($b) {
            unset($b['id']);
            if (!empty($b['children'])) {
                $b['children'] = $this->stripIds($b['children']);
            }

            return $b;
        }, $blocks);
    }
}
