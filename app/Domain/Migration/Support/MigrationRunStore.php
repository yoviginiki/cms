<?php

namespace App\Domain\Migration\Support;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * File-backed run records for admin-triggered migration tools, kept next to
 * the tools' other artifacts under storage/app/migration/{site slug}/runs.
 * No DB table: runs are operational scratch state, and the artifact directory
 * is already the tools' home (redirects.*, diff-report.*).
 */
class MigrationRunStore
{
    public static function dir(Site $site): string
    {
        return storage_path('app/migration/' . $site->slug . '/runs');
    }

    public static function artifactDir(Site $site): string
    {
        return storage_path('app/migration/' . $site->slug);
    }

    public static function create(Site $site, string $tool, string $origin, array $options): array
    {
        $run = [
            'id' => Str::uuid()->toString(),
            'tool' => $tool,
            'origin' => $origin,
            'options' => $options,
            'status' => 'queued',
            'log' => [],
            'result' => null,
            'error' => null,
            'created_at' => now()->toIso8601String(),
            'finished_at' => null,
        ];
        self::write($site, $run);

        return $run;
    }

    public static function get(Site $site, string $runId): ?array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $runId)) {
            return null;
        }
        $path = self::dir($site) . "/{$runId}.json";
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @return array<int, array> newest first */
    public static function all(Site $site, int $limit = 20): array
    {
        $dir = self::dir($site);
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob("{$dir}/*.json") ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $runs = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $data = json_decode((string) file_get_contents($f), true);
            if (is_array($data)) {
                // keep the list light — drop the full log/result payloads
                $runs[] = array_merge($data, [
                    'log' => array_slice($data['log'] ?? [], -3),
                    'result' => $data['result'] !== null ? ['summary' => $data['result']['summary'] ?? null] : null,
                ]);
            }
        }

        return $runs;
    }

    public static function write(Site $site, array $run): void
    {
        File::ensureDirectoryExists(self::dir($site));
        file_put_contents(
            self::dir($site) . "/{$run['id']}.json",
            json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function append(Site $site, string $runId, callable $mutator): void
    {
        $run = self::get($site, $runId);
        if ($run === null) {
            return;
        }
        self::write($site, $mutator($run));
    }
}
