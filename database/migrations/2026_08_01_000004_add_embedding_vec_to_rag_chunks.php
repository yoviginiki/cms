<?php

use App\Models\RagChunkRecord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additive pgvector column for Sumi (RAG). Adds `embedding_vec vector(1024)`
 * ALONGSIDE the existing `embedding` jsonb — nothing is replaced or dropped, so
 * the running PHP-cosine path keeps working untouched. D4 (jsonb + PHP cosine)
 * stays in force; this column is populated (backfill / dual-write) and validated
 * behind a parity test before any read is switched over.
 *
 * N = 1024 is the production embedder dimension (voyage-3; VoyageEmbedder::$dims),
 * kept in one place as RagChunkRecord::VECTOR_DIMS. Offline hash-16 rows are NOT
 * written into this column (RagStore only dual-writes when the embedding length
 * matches VECTOR_DIMS), so a mixed-dimension table stays valid: short rows simply
 * keep using jsonb.
 *
 * Requires the `vector` extension to already exist on the target database — it is
 * NOT created here, because the app role is not a superuser. Test/dev has it
 * (0.6.0); production is installed by hand per docs/pgvector-production-install.md
 * BEFORE this migration is run there. No HNSW index yet: that is approximate and
 * belongs to a separate gate, after exact-parity is proven.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'")) {
            throw new RuntimeException(
                'pgvector extension is not installed on this database. A superuser must run '
                . '`CREATE EXTENSION vector;` first — see docs/pgvector-production-install.md. '
                . 'This migration is additive and does not create the extension (the app role is not a superuser).'
            );
        }

        DB::statement('ALTER TABLE rag_chunks ADD COLUMN embedding_vec vector(' . RagChunkRecord::VECTOR_DIMS . ')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE rag_chunks DROP COLUMN IF EXISTS embedding_vec');
    }
};
