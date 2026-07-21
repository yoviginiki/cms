<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('term', 80);
            $table->date('day');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'term', 'day']);
            $table->index(['site_id', 'day']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE search_terms ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE search_terms FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON search_terms
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON search_terms');
        }
        Schema::dropIfExists('search_terms');
    }
};
