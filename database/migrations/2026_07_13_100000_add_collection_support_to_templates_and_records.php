<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track G2 — generalize collection templates from posts to Collections:
 *
 *  - theme_templates.collection_id: scopes the new `record-single` /
 *    `record-archive` template types to one collection (nullOnDelete — a
 *    deleted collection leaves the template unassigned, not destroyed).
 *  - records.needs_republish(+reason): the same delta-publish flags pages and
 *    posts carry, so a record edit republishes exactly its detail page plus
 *    the affected archive/loop pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_templates', function (Blueprint $table) {
            $table->uuid('collection_id')->nullable();
            $table->foreign('collection_id')->references('id')->on('collections')->nullOnDelete();
            $table->index(['site_id', 'type', 'collection_id'], 'idx_theme_templates_collection');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->boolean('needs_republish')->default(false);
            $table->string('needs_republish_reason')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Partial index — the stale-list query is always "WHERE needs_republish".
            DB::statement('CREATE INDEX idx_records_needs_republish ON records (site_id) WHERE needs_republish');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_records_needs_republish');
        }
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn(['needs_republish', 'needs_republish_reason']);
        });
        Schema::table('theme_templates', function (Blueprint $table) {
            $table->dropIndex('idx_theme_templates_collection');
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });
    }
};
