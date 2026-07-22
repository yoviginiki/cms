<?php

namespace App\Console\Commands;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use App\Models\Site;
use App\Support\Seeding\SystemRecordSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time migration: rebuild the men-root site's full-document (exact-copy)
 * pages into an editable block tree.
 *
 *  - Each page's <header>, each <main> section, and <footer> becomes an
 *    html-embed block (pixel-perfect — the bespoke styles.css is loaded
 *    globally via site settings).
 *  - Any section that contains an interactive tool (breath / meditation /
 *    pelvic / partner) has that tool replaced by the corresponding NATIVE
 *    app-block (breathing-pacer / meditation-timer / pelvic-trainer /
 *    partner-deck), with the breathing config parsed from the original markup.
 *
 * The result: the admin builder shows a real, editable block per section
 * (no more empty canvas), the tools are native reusable blocks, and the live
 * design stays intact. raw_html is cleared so the page publishes from blocks.
 *
 *   php artisan men-root:rebuild --dry-run
 *   php artisan men-root:rebuild --only=breath-box-breathing
 *   php artisan men-root:rebuild
 */
class MenRootRebuildCommand extends Command
{
    protected $signature = 'men-root:rebuild {--dry-run} {--only=} {--site=men-root}';
    protected $description = 'Rebuild men-root exact-copy pages into editable block trees (content embeds + native app-blocks)';

    private const TOOL_MAP = [
        'breath-tool' => 'breathing-pacer',
        'meditation-tool' => 'meditation-timer',
        'pelvic-tool' => 'pelvic-trainer',
        'partner-deck' => 'partner-deck',
    ];

    public function handle(BlockService $blocks): int
    {
        $slug = (string) $this->option('site');
        $site = null;
        SystemRecordSeeder::withRlsDisabled('sites', function () use ($slug, &$site) {
            $site = Site::where('slug', $slug)->first();
        });
        if (!$site) {
            $this->error("Site {$slug} not found");
            return self::FAILURE;
        }
        DB::unprepared("SET app.current_tenant_id = '" . preg_replace('/[^a-f0-9\-]/', '', $site->tenant_id) . "'");
        $dry = (bool) $this->option('dry-run');

        // Real pages only — skip the duplicate cms-html-pages-* set.
        $query = Page::where('site_id', $site->id)
            ->where('slug', 'not like', 'cms-html-pages%')
            ->whereNotNull('raw_html');
        if ($only = $this->option('only')) {
            $query->where('slug', $only);
        }
        $pages = $query->orderBy('slug')->get();
        $this->info(($dry ? '[DRY RUN] ' : '') . "Rebuilding {$pages->count()} page(s) for {$site->slug}");

        if (!$dry) {
            $this->configureGlobalAssets($site);
        }

        foreach ($pages as $page) {
            try {
                $tree = $this->buildTree($page->raw_html);
            } catch (\Throwable $e) {
                $this->error("  {$page->slug}: parse failed — {$e->getMessage()}");
                continue;
            }
            $counts = [];
            foreach ($tree as $n) { $counts[$n['type']] = ($counts[$n['type']] ?? 0) + 1; }
            $summary = collect($counts)->map(fn ($c, $t) => "{$c}×{$t}")->implode(', ');
            $this->line("  {$page->slug}: " . count($tree) . " blocks ({$summary})");

            if ($dry) { continue; }

            $blocks->syncBlocks($page, $tree);
            $page->editor_mode = 'block';
            $page->raw_html = null;
            $page->save();
        }

        $this->info($dry ? 'Dry run complete — no changes written.' : 'Rebuild complete.');
        return self::SUCCESS;
    }

    /** Load styles.css globally (head) + site.js and wrapper-neutralizers (body). */
    private function configureGlobalAssets(Site $site): void
    {
        $settings = $site->settings ?? [];
        $head = (string) ($settings['head_scripts'] ?? '');
        $body = (string) ($settings['body_scripts'] ?? '');

        $cssLink = '<link rel="stylesheet" href="/site-files/assets/css/styles.css">';
        if (!str_contains($head, '/site-files/assets/css/styles.css')) {
            $head .= "\n" . $cssLink;
        }
        $neutralize = '<style>/* men-root: keep bespoke design under the theme wrapper */'
            . 'main p a:not(.btn):not([class*="button"]){text-decoration:none}'
            . '@media(max-width:767px){main h1,main h2{font-size:revert !important}}'
            . '@media(max-width:480px){body{font-size:revert}}</style>';
        $siteJs = '<script defer src="/site-files/assets/js/site.js"></script>';
        foreach ([$neutralize, $siteJs] as $frag) {
            $key = str_contains($frag, 'site.js') ? '/site-files/assets/js/site.js' : 'men-root: keep bespoke';
            if (!str_contains($body, $key)) { $body .= "\n" . $frag; }
        }

        $settings['head_scripts'] = $head;
        $settings['body_scripts'] = $body;
        $site->settings = $settings;
        $site->save();
        $this->line('  Configured global styles.css + site.js + neutralizers.');
    }

    /**
     * Parse a full HTML document into an ordered list of block nodes
     * (html-embed for chrome/content, native app-blocks for tools).
     *
     * @return array<int, array>
     */
    private function buildTree(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) { throw new \RuntimeException('no <body>'); }

        $order = 0;
        $out = [];
        $push = function (array $node) use (&$out, &$order) {
            $node['order'] = $order++;
            $out[] = $node;
        };

        foreach (iterator_to_array($body->childNodes) as $child) {
            if (!$child instanceof DOMElement) { continue; }
            $tag = strtolower($child->nodeName);
            if ($tag === 'script') { continue; } // original site.js is loaded globally instead

            if ($tag === 'main') {
                foreach (iterator_to_array($child->childNodes) as $sec) {
                    if (!$sec instanceof DOMElement) { continue; }
                    foreach ($this->sectionToNodes($doc, $sec) as $node) { $push($node); }
                }
                continue;
            }

            // header / footer / stray top-level element → one embed
            $frag = $this->rewriteUrls($doc->saveHTML($child));
            if (trim(strip_tags($frag)) !== '' || str_contains($frag, '<img')) {
                $push($this->embed($frag));
            }
        }

        return $out;
    }

    /**
     * A <section>: if it contains a tool, emit the section-without-the-tool as
     * an embed (when non-trivial) followed by the native app-block; otherwise a
     * single embed of the whole section.
     *
     * @return array<int, array>
     */
    private function sectionToNodes(DOMDocument $doc, DOMElement $section): array
    {
        $xp = new DOMXPath($doc);
        foreach (self::TOOL_MAP as $cls => $type) {
            $q = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$cls} ')]";
            $toolEl = $xp->query($q, $section)->item(0);
            if (!$toolEl instanceof DOMElement) { continue; }

            $appNode = $this->appBlock($doc, $type, $toolEl);
            // Remove the tool from the section, keep the surrounding copy as embed.
            $toolEl->parentNode?->removeChild($toolEl);
            $rest = $this->rewriteUrls($doc->saveHTML($section));
            $nodes = [];
            if (trim(strip_tags($rest)) !== '') {
                $nodes[] = $this->embed($rest);
            }
            $nodes[] = $appNode;
            return $nodes;
        }

        return [$this->embed($this->rewriteUrls($doc->saveHTML($section)))];
    }

    /** Build the native app-block node, parsing config from the original markup. */
    private function appBlock(DOMDocument $doc, string $type, DOMElement $toolEl): array
    {
        $data = $type === 'breathing-pacer' ? $this->parseBreath($doc, $toolEl) : [];

        return [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'data' => $data,
            'children' => [],
        ];
    }

    /** Reconstruct the breathing-pacer config from the original tool markup. */
    private function parseBreath(DOMDocument $doc, DOMElement $tool): array
    {
        $xp = new DOMXPath($doc);
        $phases = [];
        foreach ($xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' phase-settings ')]/label", $tool) as $label) {
            $span = $xp->query("./span", $label)->item(0);
            $range = $xp->query(".//input[@type='range']", $label)->item(0);
            if (!$range instanceof DOMElement) { continue; }
            $phases[] = [
                'label' => $span ? trim($span->textContent) : 'Breathe',
                'value' => (float) ($range->getAttribute('value') ?: 3),
                'min' => (float) ($range->getAttribute('min') ?: 3),
                'max' => (float) ($range->getAttribute('max') ?: 60),
                'step' => (float) ($range->getAttribute('step') ?: 1),
                'locked' => $range->hasAttribute('disabled'),
            ];
        }
        $rounds = [];
        $defaultRounds = 5;
        foreach ($xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' round-settings ')]/button", $tool) as $btn) {
            $n = (int) trim($btn->textContent);
            if ($n > 0) { $rounds[] = $n; }
            if (str_contains(' ' . $btn->getAttribute('class') . ' ', ' selected ')) { $defaultRounds = $n; }
        }
        $soundInput = $xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' sound-toggle ')]//input", $tool)->item(0);
        $h2 = $xp->query(".//h2", $tool)->item(0);
        $eyebrow = $xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' eyebrow ')]", $tool)->item(0);

        return array_filter([
            'eyebrow' => $eyebrow ? trim($eyebrow->textContent) : null,
            'title' => $h2 ? trim($h2->textContent) : null,
            'advancedAt' => (int) ($tool->getAttribute('data-advanced-at') ?: 0),
            'soundDefault' => $soundInput instanceof DOMElement ? $soundInput->hasAttribute('checked') : true,
            'roundOptions' => $rounds ?: [3, 5, 8],
            'defaultRounds' => $defaultRounds,
            'phases' => $phases,
        ], fn ($v) => $v !== null && $v !== []);
    }

    private function embed(string $html): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'html-embed',
            'level' => 'module',
            'data' => ['html' => $html],
            'children' => [],
        ];
    }

    /**
     * Rewrite relative asset/link URLs to root-absolute so slug-hosting's
     * base-rewrite (rewriteBaseForSlugHosting) prefixes /men-root correctly.
     *  - assets:  assets/… , ../assets/…  →  /assets/…
     *  - internal page links:  foo/index.html , ../foo/index.html  →  /foo/
     *  - index.html / ./  →  /
     */
    private function rewriteUrls(string $html): string
    {
        // Exact-copy design files are referenced via the files API; the static
        // build serves them under /site-files/ (SiteFilesPublisher). Block pages
        // don't get that rewrite at publish time, so do it here.
        $html = preg_replace('#/api/v1/sites/[0-9a-f\-]{36}/files/#i', '/site-files/', $html);
        // Any stray relative asset refs → /site-files/assets/...
        $html = preg_replace('#((?:src|href|poster)=")(?:\.\./)*assets/#i', '$1/site-files/assets/', $html);
        // srcset
        $html = preg_replace('#(srcset=")(?:\.\./)*assets/#i', '$1/site-files/assets/', $html);

        // Internal links foo/bar/index.html → /foo/bar/ ; index.html → /
        $html = preg_replace_callback('#href="((?:\.\./)*)([^":]*?)index\.html"#i', function ($m) {
            $path = trim($m[2], '/');
            return 'href="/' . ($path === '' ? '' : $path . '/') . '"';
        }, $html);
        // Bare foo.html → /foo/ (rare)
        $html = preg_replace_callback('#href="((?:\.\./)*)([a-z0-9\-/]+)\.html"#i', function ($m) {
            return 'href="/' . trim($m[2], '/') . '/"';
        }, $html);

        return $html;
    }
}
