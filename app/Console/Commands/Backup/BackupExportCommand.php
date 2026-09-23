<?php

namespace App\Console\Commands\Backup;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Services\BackupBundleService;
use Illuminate\Console\Command;

class BackupExportCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'cms:backup:export {site : slug or id} {--out= : zip path (default: storage/app/backups/SLUG-DATE.zip)}';
    protected $description = 'Export a site backup bundle (manifest + asset files)';

    public function handle(BackupBundleService $bundles): int
    {
        $site = $this->resolveSite((string) $this->argument('site'));
        if (!$site) {
            $this->error('Site not found.');

            return self::FAILURE;
        }
        $out = $this->option('out') ?: storage_path('app/backups/' . $site->slug . '-' . now()->format('Ymd-His') . '.zip');
        @mkdir(dirname($out), 0775, true);
        $r = $bundles->export($site, $out);
        $this->info("Bundle written: {$r['path']} ({$r['assets']} assets, " . round($r['bytes'] / 1048576, 1) . ' MB)');
        if ($r['missing'] !== []) {
            $this->warn(count($r['missing']) . ' asset file(s) were missing on disk and are NOT in the bundle: ' . implode(', ', array_slice($r['missing'], 0, 10)));
        }

        return self::SUCCESS;
    }
}
