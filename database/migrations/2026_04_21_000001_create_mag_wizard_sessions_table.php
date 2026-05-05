<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mag_wizard_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->text('title')->nullable();
            $table->smallInteger('current_step')->default(1);
            $table->text('status')->default('active');
            $table->uuid('provisioned_issue_id')->nullable();
            $table->jsonb('step1_brief')->nullable();
            $table->jsonb('step2_structure')->nullable();
            $table->jsonb('step3_article_selection')->nullable();
            $table->jsonb('step4_analyses')->default('[]');
            $table->jsonb('step5_directions')->default('[]');
            $table->jsonb('step6_thumbnails')->default('[]');
            $table->timestampsTz();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('provisioned_issue_id')->references('id')->on('magazine_issues')->nullOnDelete();
        });

        // RLS
        DB::statement('ALTER TABLE mag_wizard_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE mag_wizard_sessions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON mag_wizard_sessions
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON mag_wizard_sessions');
        Schema::dropIfExists('mag_wizard_sessions');
    }
};
