<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builder Experience P3 — Style Presets. Named, reusable style bundles that a
 * block LINKS to (block.preset_id + local overrides), so editing the preset
 * restyles every linked block. Style values may reference design tokens
 * ($color.accent → var(--color-accent)); resolution at publish → plain static
 * CSS, zero runtime cost.
 *
 *  - kind 'element' : a full BlockStyleProps bundle for a block_type (or '*')
 *  - kind 'group'   : a partial bundle scoped to one option group (spacing |
 *                     typography | border | color …), stackable on a block
 *  - is_default     : the preset a fresh block of block_type starts on-brand with
 *
 * Site-scoped with shared system presets (site_id NULL), same RLS shape as
 * block_templates: read own + system, write own only (is_system unforgeable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('style_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id')->nullable();
            $table->string('block_type', 40)->default('*'); // block type or '*' (any)
            $table->string('kind', 16)->default('element');  // element | group
            $table->string('group', 24)->nullable();         // for kind=group: spacing|typography|border|color|…
            $table->string('name');
            $table->string('slug')->nullable();
            $table->jsonb('style')->default('{}');
            $table->boolean('is_default')->default(false);
            $table->integer('sort')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'block_type']);
            $table->index(['site_id', 'block_type', 'is_default']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE style_presets ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE style_presets FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON style_presets
            FOR ALL
            USING (site_id IS NULL OR {$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON style_presets');
        }
        Schema::dropIfExists('style_presets');
    }
};
