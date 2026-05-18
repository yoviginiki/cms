<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Allow system themes (site_id IS NULL, is_system = true) to be visible
        // to all tenants, alongside tenant-scoped site themes.
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

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON themes');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON themes
            USING (
                site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)
            )
        ");
    }
};
