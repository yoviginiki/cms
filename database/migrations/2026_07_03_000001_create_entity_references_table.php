<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            // Polymorphic source: the entity whose content CONTAINS the reference
            // (page|post|slider|magazine_doc|site|...)
            $table->string('source_type', 30);
            $table->uuid('source_id');
            // Polymorphic target: the entity being referenced
            // (asset|slider|magazine_doc|page|post|menu|theme|category|...)
            $table->string('target_type', 30);
            // Nullable: site-scope edges (theme, header/footer menu) have no single target row
            $table->uuid('target_id')->nullable();
            // Edge semantics: embeds|links|uses_asset|site_scope|lists
            $table->string('kind', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            // Forward lookup: all edges owned by a source (recomputed on save)
            $table->index(['source_type', 'source_id'], 'idx_entity_refs_source');
            // Inverse lookup: "what references target X?" — the staleness walk
            $table->index(['site_id', 'target_type', 'target_id'], 'idx_entity_refs_target');
        });

        // Uniqueness: Postgres treats NULLs as distinct in unique indexes, so a single
        // composite unique on (…target_id…) would allow duplicate site-scope rows.
        // Two partial unique indexes cover both shapes.
        DB::statement('
            CREATE UNIQUE INDEX uq_entity_refs_targeted
            ON entity_references (source_type, source_id, target_type, target_id, kind)
            WHERE target_id IS NOT NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX uq_entity_refs_sitescope
            ON entity_references (source_type, source_id, target_type, kind)
            WHERE target_id IS NULL
        ');

        // RLS: same site-scoped tenant_isolation policy as pages/posts/assets
        // (see 0001_01_01_000015_enable_row_level_security.php)
        DB::statement('ALTER TABLE entity_references ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entity_references FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON entity_references
            FOR ALL
            USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON entity_references');
        Schema::dropIfExists('entity_references');
    }
};
