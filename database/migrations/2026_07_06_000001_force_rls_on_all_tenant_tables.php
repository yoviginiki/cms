<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit FIX-A1a — close the tenant-isolation gaps found in STATUS.md §1.
 *
 * (1) Tables that had RLS ENABLED but not FORCED were bypassed by the app's
 *     owning DB role (cms_saas). FORCE makes their existing policy apply to
 *     the owner too.
 * (2) Tenant-bearing tables with NO RLS at all get ENABLE + FORCE + a
 *     tenant_isolation policy (scoped via their site/tenant/parent).
 *
 * All policies use current_setting('app.current_tenant_id', true) — the `true`
 * missing_ok flag returns NULL (0 rows) when no tenant is set, rather than
 * throwing.
 */
return new class extends Migration
{
    /** RLS already enabled + policy present; only FORCE was missing. */
    private array $forceOnly = [
        'magazines', 'magazine_pages', 'magazine_elements', 'magazine_issues',
        'mag_pages', 'mag_elements', 'mag_styles',
        'layouts', 'theme_assignments', 'theme_overrides', 'theme_versions',
    ];

    /** table => scoping predicate (used for both USING and WITH CHECK). */
    private function noRlsPolicies(): array
    {
        $tenantSites = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        $tenantDirect = "tenant_id = current_setting('app.current_tenant_id', true)::uuid";

        return [
            // direct site_id
            'activity_logs' => $tenantSites,
            'global_blocks' => $tenantSites,
            'grids' => $tenantSites,
            'grid_assignments' => $tenantSites,
            'menus' => $tenantSites,
            'page_views' => $tenantSites,
            'popups' => $tenantSites,
            'redirects' => $tenantSites,
            'search_queries' => $tenantSites,
            'tags' => $tenantSites,
            'theme_customizations' => $tenantSites,
            'theme_templates' => $tenantSites,
            // direct tenant_id
            'site_templates' => $tenantDirect,
            // via parent
            'menu_items' => "menu_id IN (SELECT id FROM menus WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))",
            'taggables' => "tag_id IN (SELECT id FROM tags WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))",
            'grid_positions' => "grid_id IN (SELECT id FROM grids WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))",
            'grid_position_blocks' => "grid_position_id IN (SELECT id FROM grid_positions WHERE grid_id IN (SELECT id FROM grids WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)))",
            'position_overrides' => "grid_position_id IN (SELECT id FROM grid_positions WHERE grid_id IN (SELECT id FROM grids WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)))",
        ];
    }

    public function up(): void
    {
        foreach ($this->forceOnly as $table) {
            if ($this->tableExists($table)) {
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            }
        }

        foreach ($this->noRlsPolicies() as $table => $predicate) {
            if (!$this->tableExists($table)) {
                continue;
            }
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                FOR ALL
                USING ({$predicate})
                WITH CHECK ({$predicate})
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->forceOnly as $table) {
            if ($this->tableExists($table)) {
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            }
        }

        foreach (array_keys($this->noRlsPolicies()) as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?",
            [$table]
        );
    }
};
