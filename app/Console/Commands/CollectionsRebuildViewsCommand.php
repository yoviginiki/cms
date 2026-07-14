<?php

namespace App\Console\Commands;

use App\Domain\Collections\Queries\ScopedViewManager;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ops: (re)build the SQL-mode scoped views (schema cq_<site>, col_/rel_
 * views) for all sites or one site. Run at deploy after provisioning the
 * cms_sql_guest role, or any time to repair drift.
 */
class CollectionsRebuildViewsCommand extends Command
{
    protected $signature = 'collections:rebuild-views {--site= : Limit to one site id or slug}';

    protected $description = 'Rebuild per-site scoped SQL views for Advanced query mode';

    public function handle(ScopedViewManager $views): int
    {
        if (!$views->guestRoleExists()) {
            $this->warn('cms_sql_guest role not found — views will be built but not grantable. Provision it first:');
            $this->line('  CREATE ROLE cms_sql_guest NOLOGIN;');
            $this->line('  GRANT cms_sql_guest TO ' . config('database.connections.pgsql.username') . ';');
            $this->line('  REVOKE ALL ON SCHEMA public FROM cms_sql_guest;');
        }

        foreach (Tenant::all() as $tenant) {
            $tid = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
            DB::unprepared("SET app.current_tenant_id = '{$tid}'");

            Site::where('tenant_id', $tenant->id)
                ->when($this->option('site'), fn ($q, $s) => $q->where('id', $s)->orWhere('slug', $s))
                ->get()
                ->each(function (Site $site) use ($views) {
                    $views->rebuildSite($site);
                    $count = count($views->viewNames($site));
                    $this->info("{$site->slug}: {$count} view(s) rebuilt");
                });
        }

        return self::SUCCESS;
    }
}
