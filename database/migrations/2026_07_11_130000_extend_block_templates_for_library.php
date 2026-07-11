<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builder Experience P1 — The Library. Extends the existing `block_templates`
 * table into a full library (kept physically as block_templates — the RLS
 * policy, save/insert flows, fresh-ID deep-copy, and copy-not-transclude test
 * already exist and are tested; a parallel table would duplicate all of that
 * and strand existing rows). Additive only: new nullable columns.
 *
 *  - kind : section | row | block-composition | module (structural granularity;
 *           was loosely inferred from block.level)
 *  - tags : free-form labels for search/filter
 *  - slug : stable handle for import/export round-trips
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_templates', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('category');
            $table->jsonb('tags')->nullable()->after('kind');
            $table->string('slug')->nullable()->after('name');
            $table->index(['site_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('block_templates', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'kind']);
            $table->dropColumn(['kind', 'tags', 'slug']);
        });
    }
};
