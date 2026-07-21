<?php

namespace App\Domain\Migration\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Asset;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The migration spider: crawls the ORIGIN's rendered pages and rebuilds each
 * matching imported page/post with native blocks — replacing whatever builder
 * shortcode soup the WXR import left behind. Adds what the XML can't provide:
 * a theme-colored title hero, the featured image (og:image), SEO meta, and
 * internal links resolved to the migrated URLs.
 */
class SpiderRebuildService
{
    public function __construct(
        private OriginInventory $inventory,
        private LiveContentExtractor $extractor,
        private LinkRewriter $links,
        private BlockService $blocks,
    ) {}

    /**
     * @param array{only?: string[], skip?: string[], dry?: bool} $options
     * @return array{done: int, skipped: int, missing: string[], empty: string[], failed: array<string,string>, link_blocks_rewritten: int, unresolved_links: string[]}
     */
    public function run(Site $site, string $originBase, array $options = [], ?\Closure $progress = null): array
    {
        $originBase = rtrim($originBase, '/');
        $originHost = parse_url($originBase, PHP_URL_HOST) ?? '';
        $only = $options['only'] ?? [];
        $skip = $options['skip'] ?? [];
        $dry = (bool) ($options['dry'] ?? false);
        $note = $progress ?? fn (string $line) => null;

        [$heroBg, $heroText] = $this->heroColors($site);
        $result = ['done' => 0, 'skipped' => 0, 'missing' => [], 'empty' => [], 'failed' => [], 'link_blocks_rewritten' => 0, 'unresolved_links' => []];

        $urls = array_values(array_filter(
            $this->inventory->collect($originBase),
            fn ($u) => in_array($u['type'], ['post', 'page'], true)
        ));
        $note(count($urls) . ' origin urls');

        foreach ($urls as $entry) {
            $path = trim(urldecode(parse_url($entry['url'], PHP_URL_PATH) ?? ''), '/');
            if ($path === '') {
                continue; // front page is a design decision, not a spider job
            }
            $slug = Slugify::slug($path);
            if (($only && !in_array($slug, $only, true)) || in_array($slug, $skip, true)) {
                $result['skipped']++;
                continue;
            }

            $model = $entry['type'] === 'post'
                ? Post::where('site_id', $site->id)->where('slug', $slug)->first()
                : Page::where('site_id', $site->id)->where('slug', $slug)->first();
            if (!$model) {
                $result['missing'][] = "{$entry['type']}:{$slug}";
                continue;
            }

            try {
                $html = Http::timeout(40)->retry(2, 500)->get($entry['url'])->body();
                $extracted = $this->extractor->extract($html, $model->title, $site);
                if ($extracted['blocks'] === []) {
                    $result['empty'][] = "{$entry['type']}:{$slug}";
                    continue;
                }

                if (!$dry) {
                    $this->applyToModel($site, $model, $entry['type'], $extracted, $heroBg, $heroText);
                }
                $note("OK {$entry['type']} /{$slug} (" . count($extracted['blocks']) . ' blocks)');
                $result['done']++;
            } catch (\Throwable $e) {
                $result['failed']["{$entry['type']}:{$slug}"] = $e->getMessage();
            }
        }

        // Internal links: resolve every origin href against the migrated content
        if (!$dry) {
            $this->links->buildMap($site);
            $result['link_blocks_rewritten'] = $this->links->rewriteSiteBlocks($site, $originHost);
            $result['unresolved_links'] = $this->links->unresolved();
        }

        return $result;
    }

    private function applyToModel(Site $site, Page|Post $model, string $type, array $extracted, string $heroBg, string $heroText): void
    {
        $content = $extracted['blocks'];

        // Title hero (the builder's h1 band the XML never carries)
        $heroInner = [[
            'id' => Str::uuid()->toString(), 'type' => 'heading', 'level' => 'module', 'order' => 0,
            'data' => ['text' => $model->title, 'level' => 'h1', 'textAlign' => 'center'],
            'style' => ['typography' => ['textColor' => $heroText]],
            'children' => [],
        ]];
        if ($type === 'post' && $model->published_at) {
            $heroInner[] = [
                'id' => Str::uuid()->toString(), 'type' => 'paragraph', 'level' => 'module', 'order' => 1,
                'data' => ['content' => '<p>' . $model->published_at->format('d.m.Y') . '</p>'],
                'style' => ['typography' => ['textColor' => $heroText, 'textAlign' => 'center', 'fontSize' => '0.95rem']],
                'children' => [],
            ];
        }
        $sections = [$this->section([$this->row([$this->column($heroInner)])], [
            'background_color' => $heroBg, 'padding_top' => '52px', 'padding_bottom' => '52px',
        ])];

        // Featured image from og:image (mapped to the imported asset when possible)
        if ($type === 'post' && !empty($extracted['meta']['og_image'])) {
            $mapped = $this->extractor->assetForUrl($site, $extracted['meta']['og_image']);
            $firstImgName = null;
            foreach (array_slice($content, 0, 2) as $b) {
                if (($b['type'] ?? '') === 'image') {
                    $firstImgName = $b['_imgname'] ?? null;
                    break;
                }
            }
            $featUrl = $mapped['url'] ?? $extracted['meta']['og_image'];
            if (!$model->featured_image) {
                $model->featured_image = $featUrl;
            }
            if (!$mapped || !$firstImgName || $mapped['original_name'] !== $firstImgName) {
                $sections[] = $this->section([$this->row([$this->column([[
                    'id' => Str::uuid()->toString(), 'type' => 'image', 'level' => 'module', 'order' => 0,
                    'data' => ['url' => $featUrl, 'alt' => $model->title, 'size' => 'full'] + ($mapped ? ['asset_id' => $mapped['asset_id']] : []),
                    'children' => [],
                ]])])], ['padding_top' => '0px', 'padding_bottom' => '0px', 'max_width' => '1000px']);
            }
        }

        $content = array_map(function ($b, $i) {
            unset($b['_imgname']);
            $b['order'] = $i;

            return $b;
        }, $content, array_keys($content));
        $sections[] = $this->section([$this->row([$this->column($content)])], [
            'max_width' => '800px', 'padding_top' => '48px', 'padding_bottom' => '48px',
        ]);

        $this->blocks->syncBlocks($model, $sections);

        // SEO meta the export didn't carry — merge, never replace (existing wins)
        $seo = $model->seo_meta ?? [];
        if (empty($seo['description']) && !empty($extracted['meta']['description'])) {
            $seo['description'] = mb_substr($extracted['meta']['description'], 0, 300);
        }
        $model->seo_meta = $seo;
        $model->needs_republish = true;
        $model->needs_republish_reason = 'Migration spider rebuild';
        $model->save();
    }

    /** Title-hero colors from the site's active theme (inverse surface). */
    private function heroColors(Site $site): array
    {
        $doc = $site->activeTheme?->document ?? \App\Models\Theme::find($site->active_theme_id)?->document ?? [];
        $bg = $doc['semantic']['color']['background']['inverse']['$value'] ?? '#1A1A2E';
        $text = $doc['semantic']['color']['text']['inverse']['$value'] ?? '#FFFFFF';

        return [$bg, $text];
    }

    private function section(array $children, array $data = []): array
    {
        return [
            'id' => Str::uuid()->toString(), 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => $data + ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1100px'],
            'children' => $children,
        ];
    }

    private function row(array $children): array
    {
        static $order = 0;

        return [
            'id' => Str::uuid()->toString(), 'type' => 'row', 'level' => 'row', 'order' => $order++,
            'data' => ['layout' => '1', 'gap' => '24px'], 'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        static $order = 0;

        return [
            'id' => Str::uuid()->toString(), 'type' => 'column', 'level' => 'column', 'order' => $order++,
            'data' => [], 'children' => $children,
        ];
    }
}
