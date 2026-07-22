<?php

namespace App\Console\Commands\Migration;

use App\Domain\Migration\Services\LinkRewriter;
use App\Domain\Migration\Services\MigrationDiffChecker;
use App\Domain\Migration\Services\OriginInventory;
use App\Models\Page;
use App\Models\Post;
use App\Support\Slugify;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrationDiffCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'migration:diff
        {site : Site slug or id}
        {origin : Origin base URL}
        {--new-base= : Base URL of the migrated site (default: its public URL)}
        {--limit=0 : Compare at most N pages (0 = all)}
        {--include-home : Also compare the two homepages}
        {--mobile : Also audit each migrated page at a phone viewport (overflow, squeezed grids, edge-flush text)}';

    protected $description = 'Forensic element-by-element diff of origin pages vs their migrated counterparts (text coverage, headings, images, internal links, meta)';

    public function handle(OriginInventory $inventory, LinkRewriter $links, MigrationDiffChecker $checker): int
    {
        $site = $this->resolveSite($this->argument('site'));
        if (!$site) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        $originBase = rtrim($this->argument('origin'), '/');
        $originHost = parse_url($originBase, PHP_URL_HOST) ?? '';
        $newBase = rtrim($this->option('new-base')
            ?: ($site->custom_domain
                ? "https://{$site->custom_domain}"
                : 'https://ensodo.eu/' . $site->slug), '/');

        $links->buildMap($site);

        $pairs = [];
        if ($this->option('include-home')) {
            $pairs[] = ['origin' => $originBase . '/', 'new' => $newBase . '/', 'label' => 'homepage'];
        }
        foreach ($inventory->collect($originBase) as $entry) {
            if (!in_array($entry['type'], ['post', 'page'], true)) {
                continue;
            }
            $path = trim(urldecode(parse_url($entry['url'], PHP_URL_PATH) ?? ''), '/');
            if ($path === '') {
                continue;
            }
            $slug = Slugify::slug($path);
            $model = $entry['type'] === 'post'
                ? Post::where('site_id', $site->id)->where('slug', $slug)->first()
                : Page::where('site_id', $site->id)->where('slug', $slug)->first();
            if (!$model) {
                continue;
            }
            $target = $links->resolvePath('/' . $path . '/');
            if ($target === null) {
                continue;
            }
            $pairs[] = [
                'origin' => $entry['url'],
                'new' => $newBase . $target,
                'label' => "{$entry['type']}:{$slug}",
            ];
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $pairs = array_slice($pairs, 0, $limit + ($this->option('include-home') ? 1 : 0));
        }
        $this->info(count($pairs) . ' page pairs to compare');

        $report = $checker->comparePairs($site, $originHost, $pairs);

        // A content diff is viewport-blind — bad mobile layouts (overflow,
        // squeezed grids, text flush against the screen edge) sail through it.
        if ($this->option('mobile')) {
            $this->info('running mobile audits…');
            foreach ($report['pages'] as &$p) {
                $pair = collect($pairs)->firstWhere('label', $p['label']);
                if (!$pair) {
                    continue;
                }
                $p['mobile'] = $this->mobileAudit($pair['new']);
            }
            unset($p);
            $flagged = array_filter($report['pages'], fn ($p) => !empty($p['mobile']['horizontalOverflow'])
                || !empty($p['mobile']['squeezedGrids'])
                || !empty($p['mobile']['sectionSeams'])
                || ($p['mobile']['edgeFlushTextBlocks'] ?? 0) > 3);
            $report['summary']['pages_with_mobile_issues'] = count($flagged);
        }

        $dir = storage_path("app/migration/{$site->slug}");
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/diff-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        File::put("{$dir}/diff-report.md", $this->markdown($report));

        $s = $report['summary'];
        $this->info("avg text coverage: {$s['avg_text_coverage']}%");
        $this->info("pages with missing headings: {$s['pages_with_missing_headings']}, images: {$s['pages_with_missing_images']}, links: {$s['pages_with_missing_links']}");
        if (isset($report['summary']['pages_with_mobile_issues'])) {
            $this->info("pages with mobile issues: {$report['summary']['pages_with_mobile_issues']}");
        }
        $this->info("report: {$dir}/diff-report.md");

        return self::SUCCESS;
    }

    private function mobileAudit(string $url): array
    {
        $script = base_path('scripts/mobile-audit.mjs');
        $out = shell_exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg($url) . ' 2>/dev/null');
        $data = json_decode((string) $out, true);

        return is_array($data) ? $data : ['error' => 'audit failed'];
    }

    private function markdown(array $report): string
    {
        $s = $report['summary'];
        $md = "# Migration diff report\n\n"
            . "Pages compared: {$s['pages']} — avg text coverage **{$s['avg_text_coverage']}%**\n\n";
        foreach ($report['pages'] as $p) {
            $flags = [];
            if (($p['error'] ?? null) !== null) {
                $md .= "## ❌ {$p['label']} — ERROR: {$p['error']}\n\n";
                continue;
            }
            if ($p['missing_headings'] !== []) {
                $flags[] = count($p['missing_headings']) . ' headings';
            }
            if ($p['missing_images'] !== []) {
                $flags[] = count($p['missing_images']) . ' images';
            }
            if (($p['missing_background_images'] ?? []) !== []) {
                $flags[] = count($p['missing_background_images']) . ' bg-images';
            }
            if ($p['missing_links'] !== []) {
                $flags[] = count($p['missing_links']) . ' links';
            }
            $ok = $flags === [] && $p['text_coverage'] >= 95;
            $md .= '## ' . ($ok ? '✅' : '⚠️') . " {$p['label']} — text {$p['text_coverage']}%"
                . ($flags ? ' — missing: ' . implode(', ', $flags) : '') . "\n";
            foreach ($p['missing_headings'] as $h) {
                $md .= "- missing heading: {$h}\n";
            }
            foreach ($p['demoted_headings'] ?? [] as $h) {
                $md .= "- heading text present but not as a heading: {$h}\n";
            }
            foreach ($p['missing_images'] as $i) {
                $md .= "- missing image: {$i}\n";
            }
            foreach ($p['missing_background_images'] ?? [] as $i) {
                $md .= "- missing background image: {$i}\n";
            }
            foreach ($p['missing_links'] as $l) {
                $md .= "- missing link: {$l}\n";
            }
            foreach ($p['missing_text_samples'] ?? [] as $t) {
                $md .= "- missing text: “{$t}…”\n";
            }
            $m = $p['mobile'] ?? null;
            if ($m && empty($m['error'])) {
                if (!empty($m['horizontalOverflow'])) {
                    $md .= "- 📱 horizontal overflow: page scrolls sideways ({$m['scrollWidth']}px on {$m['viewport']}px)\n";
                }
                foreach ($m['squeezedGrids'] ?? [] as $g) {
                    $md .= "- 📱 squeezed grid: {$g['el']} has {$g['columns']} columns of {$g['colWidth']}px\n";
                }
                foreach ($m['sectionSeams'] ?? [] as $g) {
                    $md .= "- 📱 background seam: {$g['el']} shows {$g['gap']}px of raw background below its content\n";
                }
                if (($m['edgeFlushTextBlocks'] ?? 0) > 3) {
                    $md .= "- 📱 {$m['edgeFlushTextBlocks']} text blocks flush against the screen edge (missing side padding)\n";
                }
            }
            $md .= "\n";
        }

        return $md;
    }
}
