<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site Wizard — build a COMPLETE website (site + theme + pages + menu +
 * homepage + media) from an existing design: a live URL that gets crawled, or
 * an uploaded ZIP of exported HTML/CSS (e.g. a Canva website export).
 *
 * Sibling of page_wizard_sessions / theme_wizard_sessions (same RLS/jsonb
 * pattern), but tenant-level: the wizard CREATES the site, so there is no
 * site_id until the create_site step has run.
 *
 * The build runs as a resumable step pipeline on the queue (one step — or one
 * page batch — per job invocation): `steps` is the checklist the SPA renders,
 * `sources` the per-page work list with per-page status. Everything the wizard
 * produces is a normal editable record (Site, Theme, Pages, Menu) — accept
 * publishes the pages, abandon deletes the whole site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_wizard_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->uuid('site_id')->nullable();       // created by the create_site step
            $table->text('title')->nullable();          // detected site name
            $table->text('status')->default('running'); // running|failed|review|accepted|abandoned
            $table->text('source');                     // url|zip
            $table->text('reference_url')->nullable();
            $table->text('workspace_path')->nullable(); // zip extraction dir (relative to storage/app)
            $table->jsonb('options')->default('{}');    // {max_pages, name, ai_polish}
            $table->jsonb('steps')->default('[]');      // [{key,label,status,detail,at}]
            $table->jsonb('sources')->default('[]');    // [{ref,slug,title,is_home,depth,page_id,status,error}]
            $table->jsonb('style_signals')->nullable(); // computed-style signals from the entry page
            $table->jsonb('profile')->nullable();       // deterministic TokenProfile fed to the compiler
            $table->jsonb('nav')->nullable();           // [{label, href}] read from the entry page
            $table->jsonb('asset_map')->default('{}');  // original url/path → {asset_id, url}
            $table->uuid('theme_id')->nullable();
            $table->uuid('menu_id')->nullable();
            $table->jsonb('page_ids')->default('[]');
            $table->jsonb('token_usage')->default('[]'); // AI polish only
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            // theme_id / menu_id are plain uuids (no FK): the themes/menus RLS
            // policies use the strict current_setting() variant, which errors
            // during FK validation scans where no tenant is set.
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE site_wizard_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE site_wizard_sessions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON site_wizard_sessions
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON site_wizard_sessions');
        }
        Schema::dropIfExists('site_wizard_sessions');
    }
};
