<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Housekeeping for Site Wizard workspaces (extracted design ZIPs under
 * storage/app/site-wizard). Accept/abandon clean up after themselves; this
 * sweeps what interrupted builds leave behind: any workspace whose session is
 * finished — or gone — and any session stuck unfinished for over 48 hours.
 *
 * Runs across tenants, so it bypasses RLS the same way other system commands
 * do (privileged connection, direct table access).
 */
class SiteWizardPruneCommand extends Command
{
    protected $signature = 'site-wizard:prune {--hours=48 : Age after which an unfinished session is considered stale}';

    protected $description = 'Delete stale Site Wizard workspaces and mark abandoned stale sessions';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        // Stale unfinished sessions → failed (their workspaces become sweepable).
        $stale = DB::table('site_wizard_sessions')
            ->whereIn('status', ['running'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'error' => 'The build stalled and was cleaned up — start again.']);

        // Workspaces whose session is finished or missing.
        $root = storage_path('app/site-wizard');
        $swept = 0;
        if (File::isDirectory($root)) {
            $active = DB::table('site_wizard_sessions')
                ->whereIn('status', ['running', 'review', 'failed'])
                ->where('updated_at', '>=', $cutoff)
                ->pluck('id')
                ->all();
            foreach (File::directories($root) as $dir) {
                if (!in_array(basename($dir), $active, true)) {
                    File::deleteDirectory($dir);
                    $swept++;
                }
            }
        }

        $this->info("Marked {$stale} stale session(s) failed, swept {$swept} workspace(s).");

        return self::SUCCESS;
    }
}
