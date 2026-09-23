<?php

namespace App\Console\Commands\Backup;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Services\BackupBundleService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'cms:backup:restore {bundle : zip path} {site : EMPTY target site (slug or id)} {--no-variants : skip WebP/responsive regeneration}';
    protected $description = 'Restore a backup bundle into an empty site (one transaction; files removed on failure)';

    public function handle(BackupBundleService $bundles): int
    {
        $site = $this->resolveSite((string) $this->argument('site'));
        if (!$site) {
            $this->error('Target site not found.');

            return self::FAILURE;
        }
        try {
            $report = $bundles->restore((string) $this->argument('bundle'), $site);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $regen = $report['_regenerate'] ?? [];
        unset($report['_regenerate']);
        foreach ($report as $k => $v) {
            $this->line(sprintf('  %-18s %s', $k, is_bool($v) ? ($v ? 'yes' : 'no') : $v));
        }
        if (!$this->option('no-variants') && $regen !== []) {
            $this->info('Image variants regenerated: ' . $bundles->regenerateVariants($regen));
        }

        return self::SUCCESS;
    }
}
