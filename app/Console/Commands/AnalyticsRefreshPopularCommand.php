<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Services\PopularityService;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes "most read" popularity for sites that have it enabled and are due
 * per their own cadence (settings.popularity.cadence + last_run). Scheduled
 * frequently; each site self-gates, so a daily site only runs once a day.
 *
 *   php artisan analytics:refresh-popular              # all due sites
 *   php artisan analytics:refresh-popular artday --force
 */
class AnalyticsRefreshPopularCommand extends Command
{
    protected $signature = 'analytics:refresh-popular
        {site? : Site slug or id (all enabled+due sites if omitted)}
        {--force : Ignore the per-site cadence and run now}
        {--no-publish : Update the signal but do not republish}';

    protected $description = 'Refresh most-read popularity from the configured analytics source and republish';

    public function handle(PopularityService $svc): int
    {
        $one = $this->argument('site');
        $force = (bool) $this->option('force');
        $publish = !$this->option('no-publish');

        // Sites are RLS-scoped per tenant; iterate tenants and set context.
        $tenants = DB::select('SELECT id FROM tenants');
        $ran = 0;
        foreach ($tenants as $t) {
            DB::statement("SET app.current_tenant_id = '{$t->id}'");
            $query = Site::query();
            if ($one) {
                $query->where(function ($q) use ($one) {
                    $q->where('slug', $one);
                    if (\Illuminate\Support\Str::isUuid($one)) {
                        $q->orWhere('id', $one);
                    }
                });
            }
            foreach ($query->get() as $site) {
                if (!$force && !$svc->isDue($site)) {
                    continue;
                }
                if (!$svc->config($site)['enabled'] && !$one) {
                    continue;
                }
                $r = $svc->refresh($site, $publish);
                $ran++;
                $this->line("{$site->slug}: " . json_encode($r));
            }
        }
        $this->info("refreshed {$ran} site(s)");

        return self::SUCCESS;
    }
}
