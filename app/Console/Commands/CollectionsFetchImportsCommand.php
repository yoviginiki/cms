<?php

namespace App\Console\Commands;

use App\Domain\Collections\Jobs\ExecuteCollectionImportJob;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Scheduled URL imports (v3): every collection with settings.import_url +
 * import_schedule pulls its CSV (the export format — headers are field keys,
 * plus optional slug/status columns) and runs a normal import job. Upsert
 * when settings.import_key names a unique field.
 */
class CollectionsFetchImportsCommand extends Command
{
    protected $signature = 'collections:fetch-imports {--collection= : Run one collection id immediately, ignoring the schedule}';

    protected $description = 'Fetch scheduled CSV imports from collection import URLs';

    private const MAX_BYTES = 50 * 1024 * 1024;

    public function handle(): int
    {
        $sites = Site::withoutGlobalScopes()->where('status', 'active')->get();

        foreach ($sites as $site) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $site->tenant_id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            $collections = ContentCollection::where('site_id', $site->id)->get()
                ->filter(fn ($c) => !empty($c->settings['import_url']));

            foreach ($collections as $collection) {
                $only = $this->option('collection');
                if ($only && $collection->id !== $only) {
                    continue;
                }
                if (!$only && !$this->isDue($collection)) {
                    continue;
                }
                $this->runImport($site, $collection);
            }
        }

        return self::SUCCESS;
    }

    private function isDue(ContentCollection $collection): bool
    {
        $schedule = $collection->settings['import_schedule'] ?? null;
        if (!in_array($schedule, ['hourly', 'daily'], true)) {
            return false;
        }
        $last = $collection->settings['import_last_run'] ?? null;
        if (!$last) {
            return true;
        }
        try {
            $lastAt = \Illuminate\Support\Carbon::parse($last);
        } catch (\Throwable) {
            return true;
        }

        // 55min/23h — a few minutes of scheduler jitter must not skip a slot.
        return $schedule === 'hourly' ? $lastAt->lte(now()->subMinutes(55)) : $lastAt->lte(now()->subHours(23));
    }

    private function runImport(Site $site, ContentCollection $collection): void
    {
        $url = $collection->settings['import_url'];

        if (!$this->hostAllowed($url)) {
            $this->warn("{$collection->slug}: import URL host resolves to a private address — skipped.");

            return;
        }

        try {
            $response = Http::timeout(60)->connectTimeout(10)
                ->withUserAgent('Stillopress-Importer/1.0')
                ->get($url);
        } catch (\Throwable $e) {
            $this->warn("{$collection->slug}: fetch failed — {$e->getMessage()}");

            return;
        }
        if (!$response->successful() || $response->body() === '' || strlen($response->body()) > self::MAX_BYTES) {
            $this->warn("{$collection->slug}: fetch failed (status {$response->status()} or empty/oversized body).");

            return;
        }

        $importId = 'url-' . Str::uuid()->toString();
        $dir = storage_path('app/collection-imports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filePath = "{$dir}/{$importId}.csv";
        file_put_contents($filePath, $response->body());

        // Header → mapping: column index => schema field key (unknown headers,
        // slug/status included, are simply not mapped — rowToInput skips them).
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle) ?: [];
        fclose($handle);
        $mapping = [];
        foreach ($headers as $i => $header) {
            $key = trim((string) $header);
            if ($collection->field($key)) {
                $mapping[$i] = $key;
            }
        }
        if ($mapping === []) {
            $this->warn("{$collection->slug}: no CSV headers match schema field keys — skipped.");
            @unlink($filePath);

            return;
        }

        $keyField = $collection->settings['import_key'] ?? null;
        $options = [
            'mapping' => $mapping,
            'mode' => $keyField ? 'upsert' : 'insert',
            'key_field' => $keyField,
            'error_policy' => 'skip',
            'status' => $collection->settings['import_status'] ?? 'draft',
            'create_missing_relations' => true,
        ];

        Cache::put("collection-import:{$importId}", [
            'import_id' => $importId,
            'status' => 'queued',
            'message' => 'Scheduled URL import queued.',
            'source' => 'url',
        ], now()->addHours(2));

        ExecuteCollectionImportJob::dispatch($site->id, $collection->id, $importId, $filePath, $options, $site->tenant_id);

        $collection->settings = array_merge($collection->settings ?? [], [
            'import_last_run' => now()->toISOString(),
            'import_last_id' => $importId,
        ]);
        $collection->save();

        $this->info("{$collection->slug}: import {$importId} queued.");
    }

    /** SSRF guard: the URL host must not resolve to private/reserved space. */
    private function hostAllowed(string $url): bool
    {
        if (config('collections.import_skip_dns_guard')) {
            return true; // tests: Http::fake hosts don't resolve
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }
        $ips = @gethostbynamel($host) ?: [];
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
