<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\ThemeAssignment;
use App\Services\Theme\ThemeResolver;
use App\Services\Theme\ValueObjects\ResolveRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmThemeCacheCommand extends Command
{
    protected $signature = 'theme:warm {--tenant= : Only this tenant} {--site= : Only this site}';
    protected $description = 'Pre-warm theme resolution caches for all active assignments';

    public function handle(ThemeResolver $resolver): int
    {
        // Set RLS context
        $tenant = DB::selectOne('SELECT id FROM tenants LIMIT 1');
        if ($tenant) {
            DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
        }

        $query = ThemeAssignment::query();

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', $tenantId);
        }
        if ($siteId = $this->option('site')) {
            $query->where('site_id', $siteId);
        }

        $assignments = $query->get();
        $count = 0;

        foreach ($assignments as $assignment) {
            try {
                $resolver->resolveFresh(new ResolveRequest(
                    tenantId: $assignment->tenant_id,
                    siteId: $assignment->site_id,
                    mode: $assignment->mode,
                ));
                $count++;
                $this->line("  Warmed: tenant={$assignment->tenant_id} site={$assignment->site_id} mode={$assignment->mode}");
            } catch (\Throwable $e) {
                $this->warn("  Failed: {$e->getMessage()}");
            }
        }

        $this->info("Warmed {$count} theme resolution(s).");
        return 0;
    }
}
