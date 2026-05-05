<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_customizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->uuid('theme_id');
            $table->string('token_key');
            $table->text('token_value');
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['site_id', 'theme_id', 'token_key']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });

        Schema::create('global_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->uuid('block_id');
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('block_id')->references('id')->on('blocks')->cascadeOnDelete();
        });

        Schema::create('popups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('trigger_type'); // load, scroll, exit_intent, click, inactivity, url_param
            $table->jsonb('trigger_config_json')->nullable();
            $table->string('frequency')->default('once'); // once, session, daily, always
            $table->string('display_type')->default('center'); // center, slide_bottom, slide_corner, fullscreen
            $table->string('animation')->default('fade'); // fade, slide_up, zoom
            $table->boolean('is_active')->default(true);
            $table->jsonb('content_json')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::create('search_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('query');
            $table->integer('results_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'created_at']);
        });

        // Add missing fields to themes table
        if (!Schema::hasColumn('themes', 'version')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->string('version')->default('1.0.0')->after('name');
                $table->jsonb('manifest_json')->nullable()->after('version');
                $table->uuid('parent_theme_id')->nullable()->after('is_system');
            });
        }

        // Add block-level features
        if (!Schema::hasColumn('blocks', 'is_locked')) {
            Schema::table('blocks', function (Blueprint $table) {
                $table->boolean('is_locked')->default(false)->after('order');
                $table->boolean('is_global')->default(false)->after('is_locked');
                $table->jsonb('visibility_rules')->nullable()->after('is_global');
                $table->jsonb('animation_json')->nullable()->after('visibility_rules');
                $table->string('custom_css_class')->nullable()->after('animation_json');
                $table->string('custom_css_id')->nullable()->after('custom_css_class');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('popups');
        Schema::dropIfExists('global_blocks');
        Schema::dropIfExists('theme_customizations');

        if (Schema::hasColumn('blocks', 'is_locked')) {
            Schema::table('blocks', function (Blueprint $table) {
                $table->dropColumn(['is_locked', 'is_global', 'visibility_rules', 'animation_json', 'custom_css_class', 'custom_css_id']);
            });
        }
    }
};
