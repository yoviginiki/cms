<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HNSW ANN index for the additive pgvector column (Sumi / RAG). Turns the exact
 * O(N) `embedding_vec <=> :q` scan into sub-linear approximate lookup — the whole
 * performance point of pgvector.
 *
 * `vector_cosine_ops` matches the cosine distance the retriever uses (and
 * RagStore::cosine). NULL embedding_vec rows (offline hash-16) are skipped by the
 * index. m/ef_construction are pgvector defaults, stated explicitly; the
 * query-time recall knob (hnsw.ef_search) belongs to the read-switch gate.
 *
 * IMPORTANT — this index makes the `<=>` path APPROXIMATE. This migration only
 * BUILDS the index; it does NOT switch any read to pgvector. The read-switch,
 * ef_search tuning, and a recall-vs-exact measurement are a separate later gate
 * (handoff steps 4–5). Until then PgvectorRetriever is used only by the parity
 * test, whose tiny dataset returns exact results regardless of the index.
 *
 * Built CONCURRENTLY so it does not lock writes on the live production table —
 * which forbids running inside a transaction, hence $withinTransaction = false.
 * Requires the `vector` extension and the embedding_vec column to already exist
 * (migration 2026_08_01_000004). Production: install the extension + run 000004
 * + backfill, then this — see docs/pgvector-handoff.md.
 */
return new class extends Migration
{
    /** CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction block. */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_rag_chunks_embedding_hnsw '
            . 'ON rag_chunks USING hnsw (embedding_vec vector_cosine_ops) '
            . 'WITH (m = 16, ef_construction = 64)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_rag_chunks_embedding_hnsw');
    }
};
