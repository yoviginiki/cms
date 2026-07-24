<?php

namespace App\Domain\Migration\Jobs;

use App\Domain\Migration\Services\LinkRewriter;
use App\Domain\Migration\Services\MigrationDiffChecker;
use App\Domain\Migration\Services\OriginInventory;
use App\Domain\Migration\Services\RedirectMapGenerator;
use App\Domain\Migration\Services\SpiderRebuildService;
use App\Domain\Migration\Support\MigrationRunStore;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Executes one admin-triggered migration tool (spider rebuild, redirect map,
 * or verification diff with optional visual screenshots) and streams progress
 * into the run's file record for the SPA to poll.
 */
class RunMigrationToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public string $siteId,
        public string $tenantId,
        public string $runId,
    ) {
    }

    public function handle(): void
    {
        // RLS: adopt the owning tenant for this worker connection.
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        \Illuminate\Support\Facades\DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $site = Site::findOrFail($this->siteId);
        $run = MigrationRunStore::get($site, $this->runId);
        if ($run === null) {
            return;
        }

        $log = function (string $line) use ($site) {
            MigrationRunStore::append($site, $this->runId, function (array $r) use ($line) {
                $r['log'][] = ['t' => now()->toTimeString(), 'line' => mb_substr($line, 0, 500)];
                $r['log'] = array_slice($r['log'], -400);

                return $r;
            });
        };

        MigrationRunStore::append($site, $this->runId, function (array $r) {
            $r['status'] = 'running';

            return $r;
        });

        try {
            $result = match ($run['tool']) {
                'spider' => $this->runSpider($site, $run, $log),
                'redirects' => $this->runRedirects($site, $run, $log),
                'diff' => $this->runDiff($site, $run, $log),
                default => throw new \RuntimeException("Unknown tool {$run['tool']}"),
            };

            MigrationRunStore::append($site, $this->runId, function (array $r) use ($result) {
                $r['status'] = 'done';
                $r['result'] = $result;
                $r['finished_at'] = now()->toIso8601String();

                return $r;
            });
        } catch (\Throwable $e) {
            logger()->warning("Migration run {$this->runId} failed: {$e->getMessage()}");
            MigrationRunStore::append($site, $this->runId, function (array $r) use ($e) {
                $r['status'] = 'failed';
                $r['error'] = mb_substr($e->getMessage(), 0, 400);
                $r['finished_at'] = now()->toIso8601String();

                return $r;
            });
        }
    }

    private function runSpider(Site $site, array $run, \Closure $log): array
    {
        $result = app(SpiderRebuildService::class)->run($site, $run['origin'], [
            'only' => $run['options']['only'] ?? [],
            'skip' => $run['options']['skip'] ?? [],
            'dry' => (bool) ($run['options']['dry'] ?? false),
        ], $log);

        return [
            'summary' => [
                'done' => $result['done'],
                'skipped' => $result['skipped'],
                'missing' => count($result['missing']),
                'empty' => count($result['empty']),
                'failed' => count($result['failed']),
                'link_blocks_rewritten' => $result['link_blocks_rewritten'],
            ],
            'missing' => $result['missing'],
            'empty' => $result['empty'],
            'failed' => $result['failed'],
            'unresolved_links' => $result['unresolved_links'],
        ];
    }

    private function runRedirects(Site $site, array $run, \Closure $log): array
    {
        $result = app(RedirectMapGenerator::class)->generate($site, $run['origin']);

        $dir = MigrationRunStore::artifactDir($site);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/redirects.htaccess", $result['htaccess']);
        File::put("{$dir}/redirects.nginx.conf", $result['nginx']);
        File::put("{$dir}/redirects.json", json_encode([
            'mapped' => $result['mapped'],
            'unmapped' => $result['unmapped'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $log(count($result['mapped']) . ' redirects written');

        if (!empty($run['options']['deploy'])) {
            $docroot = $site->custom_domain
                ? config('publishing.tenant_base') . '/' . $site->custom_domain . '/public_html'
                : config('publishing.public_path') . '/' . $site->deploySlug();
            if (is_dir($docroot)) {
                File::put("{$docroot}/.htaccess", $result['htaccess']);
                $log(".htaccess deployed to {$docroot}");
            } else {
                $log("deploy skipped — docroot missing: {$docroot}");
            }
        }

        return [
            'summary' => ['mapped' => count($result['mapped']), 'unmapped' => count($result['unmapped'])],
            'unmapped' => $result['unmapped'],
            'artifacts' => ['redirects.htaccess', 'redirects.nginx.conf', 'redirects.json'],
        ];
    }

    private function runDiff(Site $site, array $run, \Closure $log): array
    {
        $originBase = rtrim($run['origin'], '/');
        $originHost = parse_url($originBase, PHP_URL_HOST) ?? '';
        $newBase = rtrim((string) ($run['options']['new_base'] ?? '')
            ?: ($site->custom_domain
                ? "https://{$site->custom_domain}"
                : 'https://ensodo.eu/' . $site->deploySlug()), '/');

        $links = app(LinkRewriter::class);
        $links->buildMap($site);

        $pairs = [];
        if (!empty($run['options']['include_home'])) {
            $pairs[] = ['origin' => $originBase . '/', 'new' => $newBase . '/', 'label' => 'homepage'];
        }
        foreach (app(OriginInventory::class)->collect($originBase) as $entry) {
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
            $pairs[] = ['origin' => $entry['url'], 'new' => $newBase . $target, 'label' => "{$entry['type']}:{$slug}"];
        }

        $limit = (int) ($run['options']['limit'] ?? 0);
        if ($limit > 0) {
            $pairs = array_slice($pairs, 0, $limit + (!empty($run['options']['include_home']) ? 1 : 0));
        }
        $log(count($pairs) . ' page pairs to compare');

        $report = app(MigrationDiffChecker::class)->comparePairs($site, $originHost, $pairs);

        // Visual layer: screenshot each pair and score the pixel mismatch.
        if (!empty($run['options']['screenshots']) && $pairs !== []) {
            $log('capturing screenshots…');
            $shots = $this->screenshotPairs($site, $pairs, $log);
            foreach ($report['pages'] as &$p) {
                if (isset($shots[$p['label']])) {
                    $p['visual'] = $shots[$p['label']];
                }
            }
            unset($p);
            $scored = array_filter(array_column($shots, 'mismatchPct'), fn ($v) => $v !== null);
            $report['summary']['avg_visual_mismatch'] = $scored !== []
                ? round(array_sum($scored) / count($scored), 1)
                : null;
        }

        $dir = MigrationRunStore::artifactDir($site);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/diff-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $report;
    }

    /** @return array<string, array{mismatchPct: ?float, originShot: ?string, newShot: ?string, error: ?string}> keyed by pair label */
    private function screenshotPairs(Site $site, array $pairs, \Closure $log): array
    {
        $dir = MigrationRunStore::artifactDir($site) . '/shots';
        File::ensureDirectoryExists($dir);
        $pairsFile = $dir . '/pairs-' . $this->runId . '.json';
        File::put($pairsFile, json_encode($pairs, JSON_UNESCAPED_SLASHES));

        $out = shell_exec(
            'node ' . escapeshellarg(base_path('scripts/migration-shots.mjs'))
            . ' ' . escapeshellarg($pairsFile)
            . ' ' . escapeshellarg($dir) . ' 2>/dev/null'
        );
        @unlink($pairsFile);

        $rows = json_decode((string) $out, true);
        if (!is_array($rows)) {
            $log('screenshot capture failed — is Playwright available for the worker user?');

            return [];
        }

        $byLabel = [];
        foreach ($rows as $row) {
            $byLabel[$row['label']] = [
                'mismatchPct' => $row['mismatchPct'] ?? null,
                'originShot' => isset($row['originShot']) ? 'shots/' . $row['originShot'] : null,
                'newShot' => isset($row['newShot']) ? 'shots/' . $row['newShot'] : null,
                'error' => $row['error'] ?? null,
            ];
            $log($row['label'] . ': ' . (isset($row['mismatchPct']) ? $row['mismatchPct'] . '% visual mismatch' : ($row['error'] ?? '?')));
        }

        return $byLabel;
    }
}
