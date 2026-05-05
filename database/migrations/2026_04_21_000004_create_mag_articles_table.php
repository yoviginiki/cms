<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mag_articles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('issue_id');
            $table->string('slug');
            $table->string('title');
            $table->integer('page_count')->default(2);
            $table->string('rhythm', 20)->nullable(); // dense|medium|breath
            $table->text('role')->nullable();
            $table->jsonb('wizard_plan')->nullable(); // PHASE_12_PORT: jsonb -> json for MySQL
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->index(['issue_id', 'sort_order']);
            $table->unique(['issue_id', 'slug']);
        });

        // RLS via issue -> site -> tenant chain
        DB::statement('ALTER TABLE mag_articles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE mag_articles FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON mag_articles
            FOR ALL
            USING (
                issue_id IN (
                    SELECT id FROM magazine_issues WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                )
            )
            WITH CHECK (
                issue_id IN (
                    SELECT id FROM magazine_issues WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON mag_articles');
        Schema::dropIfExists('mag_articles');
    }
};
