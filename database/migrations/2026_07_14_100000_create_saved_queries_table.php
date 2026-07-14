<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track G-Q — Saved Queries: one object, two authoring modes.
 *  - mode 'simple': `definition` holds the validated visual-builder JSON,
 *    compiled server-side to safe Eloquent (SimpleQueryCompiler).
 *  - mode 'sql' (G-Q2): `sql` holds a SELECT-only statement executed under
 *    the restricted role against per-tenant scoped views.
 * Both produce the same result-shape contract, so results feed the same
 * blocks (query-stat / query-table / record-loop) and public endpoints.
 *
 * `public_params` declares the ONLY request parameters a public endpoint
 * accepts ([{key,type,required,default}]) — undeclared params are rejected.
 * Plain owner RLS (no system rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('mode', 10)->default('simple'); // simple | sql
            $table->jsonb('definition')->default('{}');
            $table->text('sql')->nullable();
            $table->jsonb('public_params')->default('[]');
            $table->boolean('is_public')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->unique(['site_id', 'slug']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE saved_queries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE saved_queries FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON saved_queries
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON saved_queries');
        }
        Schema::dropIfExists('saved_queries');
    }
};
