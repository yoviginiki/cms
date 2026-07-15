<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page Wizard — AI-assisted page creation from a reference (URL screenshot,
 * uploaded screenshot, URL content, or a plain description). Sibling of
 * theme_wizard_sessions (same RLS/jsonb/status pattern).
 *
 *  - mode 'layout'  : replicate the visual structure of a reference (vision)
 *  - mode 'content' : lay a URL's extracted text/images into a page
 *  - mode 'describe': generate from a written description
 *
 * `manifest` holds the current validated block-manifest; `page_id` is the real
 * DRAFT page created on first generation (previewed live, refined by nudges,
 * kept on accept / deleted on abandon) — so the output is always a normal,
 * editable page, never a parallel format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_wizard_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('site_id');
            $table->uuid('user_id');
            $table->text('title')->nullable();
            $table->text('status')->default('drafting'); // capturing|capture_failed|drafting|accepted|abandoned
            $table->text('source')->default('url');        // url|upload|describe
            $table->text('mode')->default('layout');       // layout|content|describe
            $table->text('reference_url')->nullable();
            $table->jsonb('transcript')->default('[]');    // [{role, text, at}]
            $table->jsonb('manifest')->nullable();          // current validated block-manifest
            $table->uuid('page_id')->nullable();            // the real draft page
            $table->jsonb('token_usage')->default('[]');
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'site_id']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE page_wizard_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE page_wizard_sessions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON page_wizard_sessions
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON page_wizard_sessions');
        }
        Schema::dropIfExists('page_wizard_sessions');
    }
};
