<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('record_id')->constrained('records')->cascadeOnDelete();
            $table->string('event', 20); // created | updated | deleted | restored
            $table->string('title', 500);
            $table->string('slug', 500);
            $table->string('status', 20);
            $table->jsonb('data')->default('{}');
            $table->jsonb('relations')->default('{}'); // relation_key => [{id,pivot,position}]
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['record_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE record_revisions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE record_revisions FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON record_revisions
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON record_revisions');
        }
        Schema::dropIfExists('record_revisions');
    }
};
