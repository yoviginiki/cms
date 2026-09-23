<?php

namespace App\Services;

use App\Domain\Assets\Services\AssetService;
use App\Domain\Assets\Services\SvgSanitizer;
use App\Http\Requests\UploadAssetRequest;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Backup BUNDLE = the 2.0.0 manifest + the asset files (F19 follow-up,
 * audit 2026-09-22). The JSON backup alone carried asset metadata only; a
 * restored site pointed at files that did not exist in the target.
 *
 * Layout:  manifest.json
 *          assets/{source-asset-id}.{ext}      (original bytes; variants are regenerated)
 *
 * Restore (into an EMPTY site) verifies every file against its checksum,
 * refuses anything but manifest.json + assets/<uuid>.<allowed-ext>, stores
 * the files under the target site, creates Asset rows with NEW ids and
 * rewrites every reference in the manifest (asset ids and the source site id
 * inside serve URLs) before handing it to BackupExportService::restore().
 * Files written before a failed DB restore are removed again.
 */
class BackupBundleService
{
    public const MAX_TOTAL_BYTES = 4 * 1024 * 1024 * 1024; // 4 GB uncompressed

    public function __construct(private BackupExportService $backup)
    {
    }

    /** @return array{path:string,assets:int,missing:array<int,string>,bytes:int} */
    public function export(Site $site, string $zipPath): array
    {
        $manifest = $this->backup->export($site);
        $disk = Storage::disk('assets');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create {$zipPath}");
        }

        $missing = [];
        $count = 0;
        foreach (Asset::where('site_id', $site->id)->get() as $asset) {
            $ext = strtolower(pathinfo((string) $asset->storage_path, PATHINFO_EXTENSION));
            $abs = $disk->path((string) $asset->storage_path);
            if (!is_file($abs)) {
                $missing[] = $asset->id;
                continue;
            }
            $zip->addFile($abs, "assets/{$asset->id}.{$ext}");
            $zip->setCompressionName("assets/{$asset->id}.{$ext}", ZipArchive::CM_STORE); // media is already compressed
            $count++;
        }
        $manifest['bundle'] = ['assets_included' => $count, 'assets_missing' => $missing];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $zip->close();

        return ['path' => $zipPath, 'assets' => $count, 'missing' => $missing, 'bytes' => (int) filesize($zipPath)];
    }

    /** @return array<string,mixed> restore report (BackupExportService counts + assets) */
    public function restore(string $zipPath, Site $target): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \InvalidArgumentException('Not a readable zip file.');
        }

        try {
            // ── pre-flight: allowed entries only, bounded size ──────────────
            $allowedExt = UploadAssetRequest::DEFAULT_EXTENSIONS;
            $files = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $name = (string) $st['name'];
                if ($name === 'manifest.json' || $name === 'assets/') {
                    continue;
                }
                if (!preg_match('#^assets/([0-9a-f-]{36})\.([a-z0-9]{1,5})$#', $name, $m) || !in_array($m[2], $allowedExt, true)) {
                    throw new \InvalidArgumentException("Unexpected entry in bundle: {$name}");
                }
                $total += (int) $st['size'];
                if ($total > self::MAX_TOTAL_BYTES) {
                    throw new \InvalidArgumentException('Bundle exceeds the size limit.');
                }
                $files[$m[1]] = $name;
            }
            $manifestJson = $zip->getFromName('manifest.json');
            if ($manifestJson === false) {
                throw new \InvalidArgumentException('Bundle has no manifest.json.');
            }
            $manifest = json_decode($manifestJson, true);
            if (!is_array($manifest)) {
                throw new \InvalidArgumentException('manifest.json is not valid JSON.');
            }
            $check = $this->backup->validateForRestore($manifest);
            if (!$check['can_restore']) {
                throw new \InvalidArgumentException('Manifest cannot be restored: ' . implode('; ', $check['errors']));
            }

            // ── store files + create assets (new ids) ─────────────────────
            $disk = Storage::disk('assets');
            $written = [];
            $idMap = [];
            $created = [];

            return DB::transaction(function () use ($zip, $manifest, $manifestJson, $files, $target, $disk, &$written, &$idMap, &$created) {
                try {
                    foreach ($manifest['assets'] ?? [] as $meta) {
                        $oldId = (string) ($meta['id'] ?? '');
                        if (!isset($files[$oldId])) {
                            continue; // metadata without bytes (was missing at export) → skipped, reported
                        }
                        $bytes = $zip->getFromName($files[$oldId]);
                        if ($bytes === false || hash('sha256', $bytes) !== ($meta['checksum'] ?? null)) {
                            throw new \InvalidArgumentException("Checksum mismatch for asset {$oldId}.");
                        }
                        $mime = (string) ($meta['mime_type'] ?? 'application/octet-stream');
                        if (UploadAssetRequest::isActiveContentMime($mime)) {
                            throw new \InvalidArgumentException("Active content refused for asset {$oldId}.");
                        }
                        if ($mime === 'image/svg+xml') {
                            $bytes = app(SvgSanitizer::class)->sanitize($bytes);
                            if ($bytes === null) {
                                throw new \InvalidArgumentException("SVG asset {$oldId} could not be sanitized.");
                            }
                        }
                        $newId = (string) Str::uuid();
                        $ext = pathinfo($files[$oldId], PATHINFO_EXTENSION);
                        $path = "sites/{$target->id}/assets/{$newId}.{$ext}";
                        $disk->put($path, $bytes);
                        $written[] = $path;

                        $asset = new Asset([
                            'site_id' => $target->id, 'folder' => $meta['folder'] ?? null,
                            'original_name' => (string) ($meta['original_name'] ?? "{$newId}.{$ext}"),
                            'storage_path' => $path, 'mime_type' => $mime, 'file_size' => strlen($bytes),
                            'dimensions' => $meta['dimensions'] ?? null, 'variants' => [],
                            'checksum' => hash('sha256', $bytes), 'alt_text' => $meta['alt_text'] ?? null,
                        ]);
                        $asset->id = $newId;
                        $asset->save();
                        $idMap[$oldId] = $newId;
                        $created[] = $asset;
                    }

                    // Rewrite every reference: old asset ids → new ids, and the
                    // source site id (serve URLs) → target site id. UUIDs are
                    // unique strings, so textual replacement is exact.
                    $replace = $idMap;
                    if (!empty($manifest['site']['id'])) {
                        $replace[(string) $manifest['site']['id']] = (string) $target->id;
                    }
                    $rewritten = json_decode(strtr($manifestJson, $replace), true);

                    $report = $this->backup->restore($rewritten, $target);
                    $report['assets'] = count($idMap);
                    $report['assets_skipped'] = count($manifest['assets'] ?? []) - count($idMap);

                    return $report;
                } catch (\Throwable $e) {
                    foreach ($written as $p) {
                        $disk->delete($p);
                    }
                    throw $e;
                }
            }) + ['_regenerate' => array_map(fn ($a) => $a->id, $created)];
        } finally {
            $zip->close();
        }
    }

    /** Best-effort WebP/responsive variants for restored images (after commit). */
    public function regenerateVariants(array $assetIds): int
    {
        $n = 0;
        foreach (Asset::whereIn('id', $assetIds)->where('mime_type', 'like', 'image/%')->where('mime_type', '!=', 'image/svg+xml')->get() as $asset) {
            if (app(AssetService::class)->regenerateVariants($asset) !== []) {
                $n++;
            }
        }

        return $n;
    }
}
