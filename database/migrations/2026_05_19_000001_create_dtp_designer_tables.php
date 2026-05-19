<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MP2 — Magazine DTP Designer schema.
 *
 * Creates production tables for the DTP page-layout editor.
 * Does NOT modify existing magazine/mag_pages/mag_elements tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Spreads ───
        Schema::create('magazine_spreads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->unsignedInteger('spread_index');
            $table->string('name')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->unique(['issue_id', 'spread_index']);
            $table->index('issue_id');
        });

        // ─── Pages ───
        Schema::create('magazine_dtp_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->uuid('spread_id')->nullable();
            $table->unsignedInteger('page_index');
            $table->string('side', 10)->default('single'); // single, left, right
            $table->unsignedInteger('width')->default(595);
            $table->unsignedInteger('height')->default(842);
            $table->jsonb('bleed')->nullable();
            $table->jsonb('margins')->nullable();
            $table->jsonb('safe_area')->nullable();
            $table->jsonb('background')->nullable();
            $table->uuid('master_page_id')->nullable(); // future FK
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('spread_id')->references('id')->on('magazine_spreads')->nullOnDelete();
            $table->unique(['issue_id', 'page_index']);
            $table->index('issue_id');
            $table->index('spread_id');
        });

        // ─── Layers ───
        Schema::create('magazine_layers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->uuid('page_id')->nullable();
            $table->string('name');
            $table->integer('layer_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('magazine_dtp_pages')->nullOnDelete();
            $table->index('issue_id');
            $table->index('page_id');
        });

        // ─── Frames ───
        Schema::create('magazine_frames', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->uuid('spread_id')->nullable();
            $table->uuid('page_id')->nullable();
            $table->uuid('layer_id')->nullable();
            $table->string('frame_type', 30); // text, image, shape, line, quote, pageNumber, articleReference, decorative
            $table->string('name')->nullable();
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->float('width')->default(200);
            $table->float('height')->default(100);
            $table->float('rotation')->default(0);
            $table->integer('z_index')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);
            $table->jsonb('content')->nullable();
            $table->jsonb('style')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('spread_id')->references('id')->on('magazine_spreads')->nullOnDelete();
            $table->foreign('page_id')->references('id')->on('magazine_dtp_pages')->nullOnDelete();
            $table->foreign('layer_id')->references('id')->on('magazine_layers')->nullOnDelete();
            $table->index('issue_id');
            $table->index('page_id');
            $table->index('spread_id');
            $table->index('layer_id');
            $table->index(['issue_id', 'page_id', 'z_index']);
        });

        // ─── Asset References ───
        Schema::create('magazine_asset_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->uuid('frame_id')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('alt')->nullable();
            $table->string('caption')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('frame_id')->references('id')->on('magazine_frames')->nullOnDelete();
            $table->index('issue_id');
            $table->index('frame_id');
        });

        // ─── RLS Policies (tenant isolation via magazine_issues → tenant_id) ───
        if (DB::connection()->getDriverName() === 'pgsql') {
            $tables = ['magazine_spreads', 'magazine_dtp_pages', 'magazine_layers', 'magazine_frames', 'magazine_asset_references'];
            foreach ($tables as $tbl) {
                DB::unprepared("ALTER TABLE {$tbl} ENABLE ROW LEVEL SECURITY");
                DB::unprepared("ALTER TABLE {$tbl} FORCE ROW LEVEL SECURITY");
                DB::unprepared("
                    CREATE POLICY tenant_isolation ON {$tbl}
                    USING (issue_id IN (
                        SELECT id FROM magazine_issues
                        WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    ))
                ");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_asset_references');
        Schema::dropIfExists('magazine_frames');
        Schema::dropIfExists('magazine_layers');
        Schema::dropIfExists('magazine_dtp_pages');
        Schema::dropIfExists('magazine_spreads');
    }
};
