<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track G — Record relations: edges between records for schema fields of type
 * 'relation' (one-to-many / many-to-many), keyed by the field key on the FROM
 * side. `pivot` carries typed fields living on the relation itself (e.g. a
 * part↔supplier edge holding supplier-part-number + supplier price), validated
 * against the field's pivot_fields schema like any record data.
 *
 * Distinct from entity_references: this is the user's data model (queryable,
 * published), while entity_references edges derived from these rows drive
 * staleness/delete-protection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->uuid('from_record_id');
            $table->uuid('to_record_id');
            $table->string('relation_key', 60);
            $table->jsonb('pivot')->default('{}');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('from_record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->foreign('to_record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->unique(['from_record_id', 'relation_key', 'to_record_id'], 'uq_record_relations_edge');
            $table->index(['to_record_id'], 'idx_record_relations_reverse');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE record_relations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE record_relations FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON record_relations
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON record_relations');
        }
        Schema::dropIfExists('record_relations');
    }
};
