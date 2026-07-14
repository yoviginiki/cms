<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track G — Collections. A user-defined structured data type: a schema of
 * typed fields (stored as JSON), whose rows live in `records`. Each collection
 * publishes in one of two tiers:
 *
 *  - tier 'static'  : flat detail pages + static JSON search index at publish
 *  - tier 'dynamic' : read-only public API feeds search/filter islands
 *
 * `schema` holds { fields: [...], title_field, slug_source } — field defs are
 * validated by CollectionSchemaValidator, never trusted raw. Site-scoped with
 * the block_templates/style_presets RLS shape: read own + system (site_id
 * NULL), write own only — `is_system` is unforgeable through the app
 * connection (WITH CHECK blocks NULL-site writes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('icon', 60)->nullable();
            $table->string('tier', 10)->default('static'); // static | dynamic
            $table->jsonb('schema')->default('{}');
            $table->jsonb('settings')->default('{}');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'tier']);
        });

        // Slug unique per site; NULL site (system) rows unique among themselves —
        // partial indexes because Postgres treats NULLs as distinct.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX uq_collections_site_slug ON collections (site_id, slug) WHERE site_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX uq_collections_system_slug ON collections (slug) WHERE site_id IS NULL');
        } else {
            Schema::table('collections', function (Blueprint $table) {
                $table->unique(['site_id', 'slug']);
            });
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE collections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE collections FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON collections
            FOR ALL
            USING (site_id IS NULL OR {$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON collections');
        }
        Schema::dropIfExists('collections');
    }
};
