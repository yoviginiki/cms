<?php

namespace App\Domain\Grid\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\StalenessResolver;
use App\Models\Block;
use App\Models\EntityReference;
use App\Models\GlobalSection;
use App\Models\Grid;
use App\Models\GridPosition;
use App\Models\Page;
use App\Models\Post;
use App\Models\PositionOverride;
use App\Models\Site;
use App\Support\Blocks\SocialIcons;
use Illuminate\Support\Facades\DB;

/**
 * Grid areas as blocks — stage 5: convert an EXISTING site's legacy grid
 * areas (menu / widget areas) into Global Section areas, so they are edited
 * with blocks like new sites — without changing the design:
 *
 *  - menu areas  → a menu block in "site design" mode (render=site): the very
 *                  same MenuRenderer output, now inside an editable section
 *  - widget areas→ the matching real blocks (plan §6); widget titles become
 *                  headings. These can look different — the parity report
 *                  says where.
 *  - left alone: empty fixed areas, fixed areas with a partial or position
 *    blocks, static/canvas/query areas, the footer of rich_footer sites,
 *    and areas with per-page overrides (reported for manual handling).
 *
 * Blocks are placed at the section ROOT (no section/row/column wrappers),
 * so no container padding is added around the old output.
 * The old type/config is kept in config_json.legacy → rollback() restores it.
 */
class GridAreaConverter
{
    /** PositionRenderer::renderBuiltinWidget's fallback headings (parity). */
    private const DEFAULT_TITLES = [
        'recent_posts' => 'Recent Posts', 'category_tree' => 'Categories', 'tag_cloud' => 'Tags',
        'newsletter' => 'Newsletter', 'author_bio' => 'About the Author', 'popular_posts' => 'Popular Posts',
        'social_links' => 'Follow Us', 'post_navigation' => 'More Posts', 'related_posts' => 'Related Posts',
        'cta_banner' => 'Ready to get started?',
    ];

    public function __construct(
        private BlockService $blocks,
        private BuildPageService $builder,
    ) {}

    /**
     * @return array<int, array{grid: string, area: string, from: string, action: string, note: string, tree: ?array, position: GridPosition}>
     */
    public function plan(Site $site): array
    {
        $plan = [];
        $richFooter = !empty($site->settings['rich_footer']);
        $grids = Grid::where('site_id', $site->id)->orderBy('name')->get();

        foreach ($grids as $grid) {
            foreach (GridPosition::where('grid_id', $grid->id)->orderBy('mobile_order')->get() as $p) {
                $c = $p->config_json ?? [];
                $from = $p->type . match ($p->type) {
                    'menu' => '(' . ($c['location'] ?? '?') . ')',
                    'widget' => '[' . implode(',', array_map(fn ($w) => $w['type'] ?? '?', $c['widgets'] ?? [])) . ']',
                    default => '',
                };
                $row = ['grid' => $grid->name, 'area' => $p->area_name, 'from' => $from, 'position' => $p, 'tree' => null, 'action' => 'keep', 'note' => ''];

                if ($p->overrides()->exists()) {
                    $row['note'] = 'has per-page overrides — convert by hand';
                } elseif ($p->type === 'menu' && !empty($c['location'])) {
                    $row['action'] = 'convert';
                    $row['tree'] = [$this->module('menu', ['source' => 'system', 'location' => $c['location'], 'render' => 'site'])];
                    $row['note'] = 'identical output (site menu design)';
                } elseif ($p->type === 'widget' && !empty($c['widgets'])) {
                    if ($p->area_name === 'footer' && $richFooter) {
                        $row['note'] = 'rich footer site — footer left as is';
                    } else {
                        [$tree, $notes] = $this->widgetTree($c['widgets'], $site);
                        if ($tree) {
                            $row['action'] = 'convert';
                            $row['tree'] = $tree;
                        }
                        $row['note'] = $notes ? implode('; ', $notes) : 'widgets → blocks (check the parity report)';
                    }
                } elseif ($p->type === 'fixed' && ($p->positionBlocks()->exists() || !empty($c['blade_partial']))) {
                    $row['note'] = 'fixed content (blocks/partial) — convert by hand';
                } elseif (in_array($p->type, ['fixed', 'widget'], true)) {
                    $row['note'] = 'empty — nothing to convert';
                }
                $plan[] = $row;
            }
        }

        return $plan;
    }

    /**
     * Convert (or, with $dryRun, only simulate inside a rolled-back
     * transaction) and compare sample pages before/after.
     *
     * @return array{plan: array, sections: array<string,string>, parity: array}
     */
    public function convert(Site $site, bool $dryRun, int $samplePages = 5): array
    {
        $samples = $this->samples($site, $samplePages);
        $before = $this->renderAll($site, $samples);

        DB::beginTransaction();
        try {
            $plan = $this->plan($site);
            $sections = [];
            $byHash = [];
            $usedNames = GlobalSection::where('site_id', $site->id)->pluck('name')->all();

            foreach ($plan as &$row) {
                if ($row['action'] !== 'convert') {
                    continue;
                }
                $hash = md5(json_encode($row['tree']));
                if (!isset($byHash[$hash])) {
                    $name = $this->uniqueName(($row['position']->label ?: ucfirst($row['area'])), $usedNames);
                    $usedNames[] = $name;
                    $section = GlobalSection::create(['site_id' => $site->id, 'name' => $name, 'status' => 'published', 'published_at' => now()]);
                    $this->blocks->syncBlocks($section, $row['tree']);
                    $byHash[$hash] = $section->id;
                    $sections[$section->id] = $name;
                }
                $p = $row['position'];
                $p->update([
                    'type' => 'section',
                    'config_json' => [
                        'section_id' => $byHash[$hash],
                        'legacy' => ['type' => $p->type, 'config' => $p->config_json ?? [], 'converted_at' => now()->toIso8601String()],
                    ],
                ]);
                $row['section'] = $sections[$byHash[$hash]];
            }
            unset($row);

            $site->update(['settings' => array_merge($site->settings ?? [], ['grid_areas' => 'blocks', 'grid_areas_converted_at' => now()->toIso8601String()])]);
            app(ReferenceRecorder::class)->recomputeSiteScope($site);

            $after = $this->renderAll($site->fresh(), $samples);

            if ($dryRun) {
                DB::rollBack();
            } else {
                app(StalenessResolver::class)->markSiteStale($site->fresh(), 'Grid areas converted to blocks');
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['plan' => $plan, 'sections' => $sections, 'parity' => $this->compare($before, $after)];
    }

    /** Restore every converted area and delete the sections the converter made (if unused elsewhere). */
    public function rollback(Site $site): array
    {
        return DB::transaction(function () use ($site) {
            $restored = 0;
            $sectionIds = [];
            $positions = GridPosition::whereIn('grid_id', Grid::where('site_id', $site->id)->pluck('id'))->where('type', 'section')->get();
            foreach ($positions as $p) {
                $legacy = $p->config_json['legacy'] ?? null;
                if (!is_array($legacy) || empty($legacy['type'])) {
                    continue;
                }
                $sectionIds[] = $p->config_json['section_id'] ?? null;
                $p->update(['type' => $legacy['type'], 'config_json' => $legacy['config'] ?? []]);
                $restored++;
            }

            app(ReferenceRecorder::class)->recomputeSiteScope($site);

            $deleted = 0;
            foreach (array_unique(array_filter($sectionIds)) as $id) {
                $stillUsed = GridPosition::where('type', 'section')->where('config_json->section_id', $id)->exists()
                    || PositionOverride::where('content_json->section_id', $id)->exists()
                    || EntityReference::where('target_type', 'global_section')->where('target_id', $id)->exists();
                if ($stillUsed) {
                    continue;
                }
                Block::where('blockable_type', 'global_section')->where('blockable_id', $id)->delete();
                GlobalSection::whereKey($id)->delete();
                $deleted++;
            }

            $settings = $site->settings ?? [];
            if (!empty($settings['grid_areas_converted_at'])) {
                unset($settings['grid_areas'], $settings['grid_areas_converted_at']);
                $site->update(['settings' => $settings]);
            }
            if ($restored) {
                app(StalenessResolver::class)->markSiteStale($site->fresh(), 'Grid area conversion rolled back');
            }

            return ['restored' => $restored, 'sections_deleted' => $deleted];
        });
    }

    // ─── widgets → blocks ───

    /** @return array{0: array, 1: array<int,string>} */
    private function widgetTree(array $widgets, Site $site): array
    {
        $tree = [];
        $notes = [];
        foreach ($widgets as $w) {
            $type = $w['type'] ?? '';
            // Same default headings the widget renderer printed when no title was set
            $title = trim((string) (array_key_exists('title', $w) ? $w['title'] : (self::DEFAULT_TITLES[$type] ?? '')));
            $blocks = match ($type) {
                'search' => [$this->module('search-box', [])],
                'recent_posts' => [$this->module('latestposts', $this->postList($w, 'latest'))],
                'popular_posts' => [$this->module('latestposts', $this->postList($w, 'popular'))],
                'latest_from_category' => [$this->module('latestposts', $this->postList($w, 'latest'))],
                'category_tree' => [$this->module('categorylist', ['style' => 'links', 'showCount' => !empty($w['show_count'])])],
                'related_posts' => [$this->module('relatedposts', ['limit' => (int) ($w['count'] ?? 3)])],
                'post_navigation' => [$this->module('post-navigation', [])],
                'author_bio' => [$this->module('authorbox', [])],
                'newsletter' => [$this->module('newsletter', array_filter(['heading' => $title, 'description' => $w['description'] ?? null]))],
                'cta_banner' => [$this->module('ctabanner', array_filter(['heading' => $title, 'buttonText' => $w['button_text'] ?? null, 'buttonUrl' => $w['button_url'] ?? null]))],
                'custom_html' => [$this->module('html-embed', ['html' => (string) ($w['html'] ?? '')])],
                'rich_text' => [$this->module('rich-text', ['content' => (string) ($w['content'] ?? '')])],
                'image' => !empty($w['src']) ? [$this->module('image', array_filter(['url' => $w['src'], 'alt' => $w['alt'] ?? '']))] : [],
                'social_links' => [$this->module('social-links', ['links' => $this->socialLinks($w['links'] ?? []), 'style' => 'circle', 'size' => 'md'])],
                'site_info', 'logo' => [$this->module('site-identity', ['show' => 'auto', 'linkHome' => true])],
                'copyright' => [$this->module('copyright', ['text' => $this->copyrightText((string) ($w['text'] ?? ''))])],
                'back_to_top' => [$this->module('back-to-top', ['style' => 'link'])],
                'tag_cloud' => [],
                default => null,
            };
            if ($blocks === null) {
                $notes[] = "widget '{$type}' has no block equivalent yet — dropped";
                continue;
            }
            // Blocks that carry their own heading don't get a second one
            if ($title !== '' && $blocks && !in_array($type, ['newsletter', 'cta_banner'], true)) {
                $tree[] = $this->module('heading', ['text' => $title, 'level' => 'h3']);
            }
            array_push($tree, ...$blocks);
            if ($type === 'tag_cloud') {
                $notes[] = 'tag cloud dropped (tags are not attachable yet, audit C3)';
            }
        }

        return [$tree, $notes];
    }

    private function postList(array $w, string $orderBy): array
    {
        return array_filter([
            'limit' => (int) ($w['count'] ?? 5),
            'categoryId' => $w['category_id'] ?? null,
            'orderBy' => $orderBy === 'popular' ? 'popular' : null,
            'layout' => 'list',
            'showImage' => !empty($w['show_image']),
            'showDate' => ($w['show_date'] ?? true) !== false,
            'showCategory' => !empty($w['show_category']),
            'showExcerpt' => false,
        ], fn ($v) => $v !== null);
    }

    private function socialLinks(array $links): array
    {
        $out = [];
        foreach ($links as $l) {
            $url = (string) ($l['url'] ?? '');
            $name = strtolower((string) ($l['name'] ?? ''));
            $network = 'website';
            foreach (array_keys(SocialIcons::NETWORKS) as $key) {
                if ($name === $key || str_contains($name, $key) || str_contains($url, $key . '.')) {
                    $network = $key;
                    break;
                }
            }
            if ($network === 'website' && (str_contains($url, 'twitter.') || str_contains($url, '//x.com'))) {
                $network = 'x';
            }
            $out[] = array_filter(['network' => $network, 'url' => $url, 'label' => $l['name'] ?? null]);
        }

        return $out;
    }

    private function copyrightText(string $text): string
    {
        $text = trim($text);

        return $text === '' ? '© {year} {site}. All rights reserved.' : preg_replace('/\b(19|20)\d{2}\b/', '{year}', $text, 1);
    }

    private function module(string $type, array $data): array
    {
        // Root-level module (no section/row/column wrappers → no added padding)
        return ['type' => $type, 'order' => 0, 'data' => $data];
    }

    private function uniqueName(string $base, array $used): string
    {
        $name = $base;
        $i = 2;
        while (in_array($name, $used, true)) {
            $name = "{$base} ({$i})";
            $i++;
        }

        return $name;
    }

    // ─── parity ───

    /** Homepage + a few published pages and grid-rendered posts. */
    private function samples(Site $site, int $n): array
    {
        $homeId = $site->settings['homepage_id'] ?? null;
        $pages = Page::where('site_id', $site->id)->where('status', 'published')
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$homeId ?? '00000000-0000-0000-0000-000000000000'])
            ->orderBy('sort_order')->limit($n)->get()->all();
        $eff = app(EffectiveGridResolver::class);
        $posts = Post::where('site_id', $site->id)->where('status', 'published')->latest('published_at')->limit(20)->get()
            ->filter(fn ($p) => $eff->skipReason($p, $site) === null)->take(2)->all();

        return array_merge($pages, $posts);
    }

    private function renderAll(Site $site, array $samples): array
    {
        $out = [];
        foreach ($samples as $c) {
            try {
                // Preview render: no asset copying into live docroots; the
                // overlay markers are stripped before comparing.
                $html = $this->builder->build($c->fresh(), $site->theme, $site, isPreview: true);
            } catch (\Throwable $e) {
                $html = 'RENDER ERROR: ' . $e->getMessage();
            }
            $out[(class_basename($c) === 'Post' ? 'post:' : '') . $c->slug] = $html;
        }

        return $out;
    }

    private function compare(array $before, array $after): array
    {
        $rows = [];
        foreach ($before as $key => $b) {
            $a = $after[$key] ?? '';
            $tb = $this->visibleText($b);
            $ta = $this->visibleText($a);
            similar_text($tb, $ta, $pct);
            $lb = substr_count($b, '<a ');
            $la = substr_count($a, '<a ');
            $rows[] = [
                'page' => $key,
                'text_same' => $tb === $ta,
                'text_similarity' => round($pct, 1),
                'links' => "{$lb} → {$la}",
                'only_before' => array_values(array_diff($this->lines($tb), $this->lines($ta))),
                'only_after' => array_values(array_diff($this->lines($ta), $this->lines($tb))),
            ];
        }

        return $rows;
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('/\sdata-sp-[a-z-]+="[^"]*"/', '', $html);
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/h\d|/div|/a)\b[^>]*>#i', "\n", $html)));

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n", $text)));
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
    }
}
