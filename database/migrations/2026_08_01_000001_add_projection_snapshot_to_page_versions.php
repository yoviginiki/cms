<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.5 — store the full internal projection alongside each page_version
 * for the internal consumers (Sumi / Site Health Ledger / Export). Additive,
 * nullable: existing rows are untouched, no backfill, no RLS change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->jsonb('projection_snapshot')->nullable()->after('seo_snapshot');
            $table->string('projection_hash', 64)->nullable()->after('projection_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->dropColumn(['projection_hash', 'projection_snapshot']);
        });
    }
};
