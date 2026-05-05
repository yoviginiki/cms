<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Magazine pages (multi-page documents within a CMS page)
        Schema::create('mag_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->integer('page_number');
            $table->jsonb('page_size')->default('{"width":595,"height":842}');
            $table->jsonb('margins')->default('{"top":36,"right":36,"bottom":36,"left":36}');
            $table->jsonb('bleed')->default('{"top":9,"right":9,"bottom":9,"left":9}');
            $table->jsonb('columns')->default('{"count":1,"gutter":12}');
            $table->jsonb('baseline_grid')->default('{"increment":14,"start":36}');
            $table->uuid('master_page_id')->nullable();
            $table->boolean('is_master')->default(false);
            $table->integer('spread_with')->nullable();
            $table->string('background_color', 20)->nullable();
            $table->uuid('background_asset_id')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'page_number']);
            $table->index(['page_id', 'is_master']);
        });

        // Note: master_page_id self-reference handled at application level (RLS blocks FK on self-referencing UUID tables)

        // Magazine elements (frames, shapes, etc.)
        Schema::create('mag_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->uuid('parent_id')->nullable();
            $table->string('type', 40);
            $table->string('name')->nullable();
            $table->jsonb('data')->default('{}');

            // Position & transform
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->float('width')->default(200);
            $table->float('height')->default(100);
            $table->float('rotation')->default(0);
            $table->float('scale_x')->default(1);
            $table->float('scale_y')->default(1);

            // Layer
            $table->integer('z_index')->default(0);
            $table->boolean('locked')->default(false);
            $table->boolean('visible')->default(true);
            $table->string('layer_name')->nullable();

            // Styling
            $table->jsonb('style')->default('{}');
            $table->jsonb('typography')->nullable();
            $table->jsonb('text_wrap')->default('{"type":"none","offset":{"top":0,"right":0,"bottom":0,"left":0},"side":"both"}');

            // Threading
            $table->uuid('thread_id')->nullable();
            $table->integer('thread_order')->nullable();

            // Page context
            $table->integer('page_number')->default(1);
            $table->boolean('on_master')->default(false);

            // Responsive
            $table->jsonb('responsive_overrides')->default('{}');

            // Metadata
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            // parent_id self-reference handled at application level
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['page_id', 'page_number', 'z_index']);
            $table->index(['page_id', 'thread_id', 'thread_order']);
            $table->index(['page_id', 'parent_id']);
            $table->index(['page_id', 'on_master']);
        });

        // Magazine styles (paragraph/character)
        Schema::create('mag_styles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('type', 20); // paragraph, character
            $table->jsonb('properties');
            $table->uuid('based_on')->nullable();
            $table->uuid('next_style')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            // based_on and next_style self-references handled at application level
            $table->unique(['site_id', 'type', 'name']);
        });

        // RLS
        DB::statement("ALTER TABLE mag_pages ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE mag_elements ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE mag_styles ENABLE ROW LEVEL SECURITY");

        DB::statement("CREATE POLICY tenant_isolation ON mag_pages FOR ALL USING (page_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)))");
        DB::statement("CREATE POLICY tenant_isolation ON mag_elements FOR ALL USING (page_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)))");
        DB::statement("CREATE POLICY tenant_isolation ON mag_styles FOR ALL USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))");
    }

    public function down(): void
    {
        Schema::dropIfExists('mag_elements');
        Schema::dropIfExists('mag_pages');
        Schema::dropIfExists('mag_styles');
    }
};
