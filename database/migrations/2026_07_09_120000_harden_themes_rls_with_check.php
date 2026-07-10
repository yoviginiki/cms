<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T1.2 theme-engine hardening: close the themes RLS write hole.
 *
 * The previous policy had a USING clause but NO WITH CHECK. Postgres then
 * reuses USING as the write check, and USING permits `site_id IS NULL AND
 * is_system = true` — so a tenant session could INSERT a fake "system" theme
 * that RLS then exposes to EVERY tenant's theme picker (cross-tenant injection).
 *
 * Fix: keep the permissive USING (system themes MUST stay readable by all
 * tenants) but add a strict WITH CHECK that only allows writing rows whose
 * site_id belongs to the current tenant. A tenant session therefore cannot
 * write site_id=NULL (system) rows or another tenant's rows at all.
 *
 * Legitimate system-theme seeding is unaffected: the system-theme seeder
 * (database/seeders/SystemThemeSeeder.php) DISABLEs RLS around its inserts.
 * Paired with removing `is_system` from Theme::$fillable (app-side belt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON themes');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON themes
            USING (
                (site_id IS NULL AND is_system = true)
                OR
                site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)
            )
            WITH CHECK (
                site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)
            )
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Restore the prior USING-only policy (no WITH CHECK).
        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON themes');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON themes
            USING (
                (site_id IS NULL AND is_system = true)
                OR
                site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)
            )
        ");
    }
};
