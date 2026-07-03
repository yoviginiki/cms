<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sliders are STANDALONE LIBRARY ENTITIES (like magazine documents),
        // not inline page content. Pages embed them via the slider_ref block;
        // the slider's own block tree lives in the polymorphic blocks table
        // (blockable_type = 'slider').
        Schema::create('sliders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->enum('status', ['draft', 'published'])->default('draft');
            // Convenience pointer to the root 'slider' block of the tree.
            // Deliberately NO foreign key: blocks are polymorphic/unconstrained
            // everywhere, and an FK's validation query trips the blocks RLS
            // policy (current_setting without missing_ok) during migration.
            $table->uuid('root_block_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // RLS: same site-scoped tenant_isolation policy as pages/posts/assets
        DB::statement('ALTER TABLE sliders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sliders FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON sliders
            FOR ALL
            USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
        ");

        // Extend the blocks policy with the 'slider' blockable arm (same shape
        // as the template arm added by 2026_05_18_072109)
        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
        DB::unprepared("
            CREATE POLICY tenant_isolation ON blocks
            USING (
                (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'slider' AND blockable_id IN (SELECT id FROM sliders WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
            )
            WITH CHECK (
                (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                OR (blockable_type = 'slider' AND blockable_id IN (SELECT id FROM sliders WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
            )
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // restore the blocks policy without the slider arm
            DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
            DB::unprepared("
                CREATE POLICY tenant_isolation ON blocks
                USING (
                    (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                    OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                    OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                )
                WITH CHECK (
                    (blockable_type = 'page' AND blockable_id IN (SELECT id FROM pages WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                    OR (blockable_type = 'post' AND blockable_id IN (SELECT id FROM posts WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                    OR (blockable_type = 'template' AND blockable_id IN (SELECT id FROM theme_templates WHERE site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid)))
                )
            ");
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON sliders');
        }

        Schema::dropIfExists('sliders');
    }
};
