<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumi (RAG) chunk index. Embeddings are stored as jsonb (a float array) rather
 * than a pgvector column: the DB role is not a superuser and cannot
 * `CREATE EXTENSION vector`. Similarity is computed in PHP (cosine) — correct
 * for typical site sizes. Adding a pgvector column + ANN index later is a pure
 * optimization once a DBA installs the extension.
 *
 * Site-scoped with the same tenant_isolation RLS policy as the other
 * site-owned tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('page_id', 64)->nullable();
            $table->string('page_version_id', 64)->nullable();
            $table->string('segment_id', 64);
            $table->string('address');
            $table->jsonb('heading_path')->default('[]');
            $table->text('text');
            $table->string('hash', 64);
            $table->jsonb('embedding');
            $table->string('model', 60);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            // One row per (site, content-hash): enables cheap incremental reindex.
            $table->unique(['site_id', 'hash'], 'uq_rag_chunks_site_hash');
            $table->index(['site_id', 'page_id'], 'idx_rag_chunks_site_page');
        });

        DB::statement('ALTER TABLE rag_chunks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rag_chunks FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON rag_chunks
            FOR ALL
            USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
    }
};
