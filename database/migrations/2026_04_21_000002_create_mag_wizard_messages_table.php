<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mag_wizard_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('session_id');
            $table->smallInteger('step');
            $table->text('role');
            $table->text('content');
            $table->jsonb('artifact_update')->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['session_id', 'created_at']);

            $table->foreign('session_id')->references('id')->on('mag_wizard_sessions')->cascadeOnDelete();
        });

        // Check constraint for role
        DB::statement("ALTER TABLE mag_wizard_messages ADD CONSTRAINT mag_wizard_messages_role_check CHECK (role IN ('user', 'assistant'))");

        // RLS
        DB::statement('ALTER TABLE mag_wizard_messages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE mag_wizard_messages FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON mag_wizard_messages
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON mag_wizard_messages');
        Schema::dropIfExists('mag_wizard_messages');
    }
};
