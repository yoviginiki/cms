<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S5 Forms v2 — form submissions move from flat JSON files to a real
 * tenant-scoped table (RLS FORCED like every Track G table). `form_key`
 * identifies the form block on the site ('contact' for the fixed contact
 * form); `data` holds the validated field values; `meta` request context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('form_key', 80)->default('contact');
            $table->jsonb('data')->default('{}');
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'form_key', 'created_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE form_submissions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE form_submissions FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON form_submissions
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON form_submissions');
        }
        Schema::dropIfExists('form_submissions');
    }
};
