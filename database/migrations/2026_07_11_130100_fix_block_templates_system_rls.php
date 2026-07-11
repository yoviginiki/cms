<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Builder Experience P1 — enable the system/global library-item path.
 *
 * The generic tenant_isolation policy filters `site_id IN (tenant's sites)`, so a
 * shared first-party library item (site_id = NULL, is_system = true) evaluates
 * `NULL IN (...)` → not true → invisible to everyone. This overrides the policy
 * on block_templates so tenants can READ system items (NULL site) plus their own,
 * while WRITES stay owner-only — a tenant can never insert/modify a NULL-site
 * (system) row, so `is_system` cannot be forged from the app connection.
 */
return new class extends Migration
{
    private string $ownSites = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";

    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON block_templates');
        DB::statement("
            CREATE POLICY tenant_isolation ON block_templates
            FOR ALL
            USING (site_id IS NULL OR {$this->ownSites})
            WITH CHECK ({$this->ownSites})
        ");
    }

    public function down(): void
    {
        // Restore the generic site-scoped policy (matches the base RLS migration).
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON block_templates');
        DB::statement("
            CREATE POLICY tenant_isolation ON block_templates
            FOR ALL
            USING ({$this->ownSites})
            WITH CHECK ({$this->ownSites})
        ");
    }
};
