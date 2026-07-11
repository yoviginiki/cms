<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builder Experience P2 — Global Sections. A standalone library entity (like a
 * slider): its block tree lives in the polymorphic blocks table
 * (blockable_type = 'global_section'), and pages EMBED it via the global_ref
 * block — referenced, not copied. At publish time the section's published tree
 * is inlined into the page's static output; editing + republishing the section
 * flags every embedding page stale through the existing references/staleness
 * engine (no Global-Sections-specific republish logic).
 *
 * Mirrors 2026_07_03_100000_create_sliders_table (same RLS shape + blocks arm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE global_sections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE global_sections FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON global_sections
            FOR ALL
            USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
        ");

        // Extend the blocks policy with the 'global_section' blockable arm
        // (append to page/post/template/slider — mirror the slider migration).
        DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
        DB::unprepared($this->blocksPolicy(withGlobalSection: true));
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP POLICY IF EXISTS tenant_isolation ON blocks');
            DB::unprepared($this->blocksPolicy(withGlobalSection: false));
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON global_sections');
        }

        Schema::dropIfExists('global_sections');
    }

    private function blocksPolicy(bool $withGlobalSection): string
    {
        $tenant = "current_setting('app.current_tenant_id')::uuid";
        $sites = "SELECT id FROM sites WHERE tenant_id = {$tenant}";
        $arm = fn (string $type, string $tbl) =>
            "(blockable_type = '{$type}' AND blockable_id IN (SELECT id FROM {$tbl} WHERE site_id IN ({$sites})))";

        $arms = [
            $arm('page', 'pages'),
            $arm('post', 'posts'),
            $arm('template', 'theme_templates'),
            $arm('slider', 'sliders'),
        ];
        if ($withGlobalSection) {
            $arms[] = $arm('global_section', 'global_sections');
        }
        $expr = implode("\n                OR ", $arms);

        return "
            CREATE POLICY tenant_isolation ON blocks
            USING (
                {$expr}
            )
            WITH CHECK (
                {$expr}
            )
        ";
    }
};
