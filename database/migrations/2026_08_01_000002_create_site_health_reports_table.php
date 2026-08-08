<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site Health Ledger — history of health scans (broken links now; PageSpeed and
 * stale-reference reports later) keyed by site. Site-scoped with the same
 * tenant_isolation RLS policy as pages/posts/entity_references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_health_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('type', 30);            // broken_links | pagespeed | stale_refs
            $table->jsonb('data')->default('{}');  // full report payload
            $table->jsonb('summary')->default('{}'); // small headline counts
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'type', 'created_at'], 'idx_site_health_site_type');
        });

        // RLS: same site-scoped tenant_isolation policy as entity_references.
        DB::statement('ALTER TABLE site_health_reports ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE site_health_reports FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON site_health_reports
            FOR ALL
            USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('site_health_reports');
    }
};
