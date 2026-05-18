<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;

        // Extend blocks RLS to allow template blocks (blockable_type = 'template')
        // Template blocks are accessible when the template's site_id belongs to the tenant
        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON blocks
            USING (
                (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
            )
            WITH CHECK (
                (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
            )
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;

        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON blocks
            USING (
                blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid))
                OR blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid))
                OR parent_block_id IS NOT NULL
            )
        ");
    }
};
