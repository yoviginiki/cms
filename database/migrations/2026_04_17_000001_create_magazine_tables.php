<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status', 20)->default('draft');
            $table->integer('page_width')->default(210);
            $table->integer('page_height')->default(297);
            $table->jsonb('settings')->default('{}');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('magazine_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('magazine_id');
            $table->string('title')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('background_color', 20)->nullable();
            $table->string('background_image')->nullable();
            $table->string('background_size', 20)->default('cover');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->foreign('magazine_id')->references('id')->on('magazines')->cascadeOnDelete();
            $table->index(['magazine_id', 'sort_order']);
        });

        Schema::create('magazine_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('magazine_page_id');
            $table->string('type', 30);
            $table->jsonb('content')->default('{}');
            $table->decimal('x', 8, 2)->default(0);
            $table->decimal('y', 8, 2)->default(0);
            $table->decimal('width', 8, 2)->default(100);
            $table->decimal('height', 8, 2)->default(100);
            $table->decimal('rotation', 5, 2)->default(0);
            $table->integer('z_index')->default(0);
            $table->jsonb('style')->default('{}');
            $table->timestamps();

            $table->foreign('magazine_page_id')->references('id')->on('magazine_pages')->cascadeOnDelete();
            $table->index(['magazine_page_id', 'z_index']);
        });

        // RLS policies
        DB::statement("ALTER TABLE magazines ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE magazine_pages ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE magazine_elements ENABLE ROW LEVEL SECURITY");

        DB::statement("
            CREATE POLICY tenant_isolation ON magazines FOR ALL USING (
                site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            )
        ");
        DB::statement("
            CREATE POLICY tenant_isolation ON magazine_pages FOR ALL USING (
                magazine_id IN (SELECT id FROM magazines WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            )
        ");
        DB::statement("
            CREATE POLICY tenant_isolation ON magazine_elements FOR ALL USING (
                magazine_page_id IN (SELECT id FROM magazine_pages WHERE magazine_id IN (SELECT id FROM magazines WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)))
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_elements');
        Schema::dropIfExists('magazine_pages');
        Schema::dropIfExists('magazines');
    }
};
