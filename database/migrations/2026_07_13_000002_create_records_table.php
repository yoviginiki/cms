<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track G — Records: the rows of a collection. Field values live in JSONB
 * `data`, validated/sanitized against the collection schema at the service
 * layer (RecordValidator / RecordSanitizer) — the model never trusts raw data.
 * slug/title/status are denormalized real columns so lists, publish paths and
 * uniqueness never dig into JSONB.
 *
 * site_id is denormalized from the collection (records only exist on real
 * sites — system collections carry sample data as wizard manifests, not rows)
 * so RLS is a direct owner policy and queries skip a join.
 *
 * Indexes: GIN jsonb_path_ops on data (containment lookups: unique-field
 * checks, facet filters — the first GIN index in this codebase), and a
 * pgsql-only `search_text` tsvector (GIN) maintained by RecordService from
 * searchable fields — populated for every record, queried by Tier-2 search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->uuid('site_id');
            $table->string('slug');
            $table->string('title')->nullable();
            $table->string('status', 12)->default('draft'); // draft | published
            $table->integer('position')->default(0);
            $table->jsonb('data')->default('{}');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->unique(['collection_id', 'slug']);
            $table->index(['collection_id', 'status']);
            $table->index(['site_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX idx_records_data_gin ON records USING gin (data jsonb_path_ops)');
        DB::statement('ALTER TABLE records ADD COLUMN search_text tsvector');
        DB::statement('CREATE INDEX idx_records_search_gin ON records USING gin (search_text)');

        DB::statement('ALTER TABLE records ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE records FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON records
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON records');
        }
        Schema::dropIfExists('records');
    }
};
