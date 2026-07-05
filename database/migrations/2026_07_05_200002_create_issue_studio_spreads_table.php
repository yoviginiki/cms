<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_studio_spreads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('session_id');
            $table->integer('position'); // 0 = cover, 1..n = spreads
            $table->text('status')->default('pending'); // pending|generated|approved|revising
            $table->text('working_title')->nullable();
            $table->text('section')->nullable();   // cover|fob|feature|bob
            $table->text('pattern')->nullable();   // spread-patterns.md vocabulary
            $table->jsonb('materials')->default('[]'); // brief material ids
            $table->text('intent')->nullable();
            // The generated spread lives as DTP pages inside the session's
            // magazine issue; this records which page ids the spread owns.
            $table->jsonb('page_ids')->default('[]');
            $table->jsonb('generation_notes')->default('[]');
            $table->timestampsTz();

            $table->index(['tenant_id', 'session_id']);
            $table->unique(['session_id', 'position']);

            $table->foreign('session_id')->references('id')->on('issue_studio_sessions')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE issue_studio_spreads ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE issue_studio_spreads FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON issue_studio_spreads
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON issue_studio_spreads');
        Schema::dropIfExists('issue_studio_spreads');
    }
};
