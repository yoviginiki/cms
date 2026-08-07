<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant enablement + settings for a module. A row exists once a tenant has
 * an explicit stance on a module; absence means "not enabled for this tenant".
 *
 * Tenant-scoped → standard FORCE row-level security keyed on the tenant GUC,
 * mirroring the `webhooks` / force_rls_on_all_tenant_tables convention:
 * `tenant_id = current_setting('app.current_tenant_id', true)::uuid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_tenant', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->uuid('tenant_id');
            $table->boolean('enabled')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['module_id', 'tenant_id']);
            $table->index(['module_id', 'tenant_id']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE module_tenant ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE module_tenant FORCE ROW LEVEL SECURITY');
        $own = "tenant_id = current_setting('app.current_tenant_id', true)::uuid";
        DB::statement("
            CREATE POLICY tenant_isolation ON module_tenant
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON module_tenant');
        }
        Schema::dropIfExists('module_tenant');
    }
};
