<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('col_tracks')->default('1fr');
            $table->string('row_tracks')->default('auto');
            $table->text('areas');
            $table->string('gap_x')->default('16px');
            $table->string('gap_y')->default('12px');
            $table->string('container_width')->default('1200px');
            $table->boolean('is_preset')->default(false);
            $table->jsonb('breakpoints_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::create('grid_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grid_id');
            $table->string('area_name');
            $table->string('label');
            $table->string('type'); // canvas, menu, query, fixed, widget, static
            $table->jsonb('config_json')->nullable();
            $table->string('scope')->default('site'); // site, page, grid
            $table->boolean('is_overridable')->default(false);
            $table->integer('mobile_order')->default(0);
            $table->string('min_height')->nullable();
            $table->jsonb('background_json')->nullable();
            $table->jsonb('padding_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('grid_id')->references('id')->on('grids')->cascadeOnDelete();
            $table->unique(['grid_id', 'area_name']);
        });

        Schema::create('grid_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->uuid('grid_id');
            $table->string('assignable_type'); // page, post, post_type, category, rule, default
            $table->uuid('assignable_id')->nullable();
            $table->integer('priority')->default(9999);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('grid_id')->references('id')->on('grids')->cascadeOnDelete();
            $table->index(['site_id', 'priority']);
        });

        Schema::create('position_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grid_position_id');
            $table->uuid('page_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->jsonb('content_json');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('grid_position_id')->references('id')->on('grid_positions')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->unique(['grid_position_id', 'page_id']);
        });

        Schema::create('grid_position_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grid_position_id');
            $table->uuid('block_id');
            $table->integer('order')->default(0);

            $table->foreign('grid_position_id')->references('id')->on('grid_positions')->cascadeOnDelete();
            $table->foreign('block_id')->references('id')->on('blocks')->cascadeOnDelete();
            $table->index(['grid_position_id', 'order']);
        });

        // Add grid_id to pages for direct assignment override
        Schema::table('pages', function (Blueprint $table) {
            $table->uuid('grid_id')->nullable()->after('sort_order');
            $table->foreign('grid_id')->references('id')->on('grids')->nullOnDelete();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('grid_id')->nullable()->after('scheduled_at');
            $table->foreign('grid_id')->references('id')->on('grids')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['grid_id']);
            $table->dropColumn('grid_id');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['grid_id']);
            $table->dropColumn('grid_id');
        });
        Schema::dropIfExists('grid_position_blocks');
        Schema::dropIfExists('position_overrides');
        Schema::dropIfExists('grid_assignments');
        Schema::dropIfExists('grid_positions');
        Schema::dropIfExists('grids');
    }
};
