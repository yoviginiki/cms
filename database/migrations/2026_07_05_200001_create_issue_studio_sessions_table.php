<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_studio_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('site_id');
            $table->uuid('user_id');
            $table->text('title')->nullable();
            $table->text('status')->default('interviewing'); // interviewing|flatplanning|generating|complete|abandoned
            $table->jsonb('brief')->default('{}');
            $table->jsonb('transcript')->default('[]');
            $table->jsonb('flatplan')->nullable();
            $table->uuid('magazine_issue_id')->nullable();
            $table->jsonb('token_usage')->default('[]');
            $table->timestampsTz();

            $table->index(['tenant_id', 'site_id']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('magazine_issue_id')->references('id')->on('magazine_issues')->nullOnDelete();
        });

        DB::statement('ALTER TABLE issue_studio_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE issue_studio_sessions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON issue_studio_sessions
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON issue_studio_sessions');
        Schema::dropIfExists('issue_studio_sessions');
    }
};
