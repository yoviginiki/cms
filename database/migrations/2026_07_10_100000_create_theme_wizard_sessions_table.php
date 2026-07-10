<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3 W3 — the Theme Wizard's conversational session. Sibling of
 * issue_studio_sessions (same RLS/jsonb/status pattern). Holds the source
 * (reference URL / upload / conversation), the running transcript, the current
 * design-token PROFILE, the compiled candidate theme document, and usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_wizard_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('site_id');
            $table->uuid('user_id');
            $table->text('title')->nullable();
            $table->text('status')->default('drafting'); // drafting|accepted|abandoned
            $table->text('source')->default('reference'); // reference|upload|conversation
            $table->text('reference_url')->nullable();
            $table->jsonb('transcript')->default('[]');  // [{role, text, at}]
            $table->jsonb('profile')->nullable();        // current token profile (the design read)
            $table->jsonb('candidate')->nullable();       // compiled theme document + name/slug
            $table->uuid('theme_id')->nullable();         // the real theme once accepted
            $table->jsonb('token_usage')->default('[]');
            $table->timestampsTz();

            $table->index(['tenant_id', 'site_id']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE theme_wizard_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE theme_wizard_sessions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON theme_wizard_sessions
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON theme_wizard_sessions');
        Schema::dropIfExists('theme_wizard_sessions');
    }
};
