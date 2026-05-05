<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Magazine Issues — the top-level "brief" for an AI-composed issue
        Schema::create('magazine_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('site_id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('theme')->nullable();
            $table->text('intention')->nullable();
            $table->jsonb('tone_knobs')->default('{}');
            $table->integer('target_page_count')->default(20);
            $table->string('language')->default('en');
            $table->string('status')->default('draft');
            $table->uuid('linked_page_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('linked_page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index('site_id');
        });

        // Issue Content Items — posts/assets/extras gathered for the issue
        Schema::create('issue_content_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->string('source_type');
            $table->uuid('source_id')->nullable();
            $table->jsonb('extra_payload')->nullable();
            $table->string('importance')->default('should');
            $table->string('role_hint')->default('none');
            $table->text('editor_note')->nullable();
            $table->string('ai_decision')->default('pending');
            $table->string('assigned_section_id')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();

            $table->index(['issue_id', 'position']);
        });

        // Magazine Curation Runs — append-only log of AI pipeline runs
        Schema::create('magazine_curation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->string('phase');
            $table->char('input_hash', 64);
            $table->string('claude_model')->nullable();
            $table->integer('claude_input_tokens')->default(0);
            $table->integer('claude_output_tokens')->default(0);
            $table->jsonb('output_jsonb')->default('{}');
            $table->string('prompt_version')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['issue_id', 'phase', 'created_at']);
        });

        // Issue Design System — one-to-one with the issue
        Schema::create('issue_design_system', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->jsonb('palette')->nullable();
            $table->jsonb('typography')->nullable();
            $table->jsonb('grid')->nullable();
            $table->jsonb('image_style')->nullable();
            $table->uuid('source_run_id')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->foreign('source_run_id')->references('id')->on('magazine_curation_runs')->nullOnDelete();

            $table->unique('issue_id');
        });

        // RLS policies
        DB::statement("ALTER TABLE magazine_issues ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE issue_content_items ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE magazine_curation_runs ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE issue_design_system ENABLE ROW LEVEL SECURITY");

        // Direct tenant check on magazine_issues
        DB::statement("CREATE POLICY tenant_isolation ON magazine_issues FOR ALL USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)");

        // Chain through magazine_issues for child tables
        DB::statement("CREATE POLICY tenant_isolation ON issue_content_items FOR ALL USING (issue_id IN (SELECT id FROM magazine_issues WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))");
        DB::statement("CREATE POLICY tenant_isolation ON magazine_curation_runs FOR ALL USING (issue_id IN (SELECT id FROM magazine_issues WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))");
        DB::statement("CREATE POLICY tenant_isolation ON issue_design_system FOR ALL USING (issue_id IN (SELECT id FROM magazine_issues WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))");
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_design_system');
        Schema::dropIfExists('magazine_curation_runs');
        Schema::dropIfExists('issue_content_items');
        Schema::dropIfExists('magazine_issues');
    }
};
