<?php

namespace App\Domain\Projection\Rag\Retrieval;

use App\Models\RagChunkRecord;

/**
 * Chooses the read path for a query. The pgvector path is used ONLY when the
 * kill-switch (`cms.sumi.pgvector`) is on AND the query embedding has the
 * vector column's dimension — mirroring the dual-write rule, so reads go to
 * `embedding_vec` exactly when it is populated. Everything else (switch off, or
 * a short offline hash embedding whose vectors were never written to the column)
 * stays on the jsonb PHP-cosine path. This makes flipping the switch safe even
 * before content is re-embedded at full dimension: no empty-result trap.
 *
 * PgvectorRetriever is still EXACT (no HNSW yet), so the switch is behaviourally
 * equivalent to jsonb — the parity test proves it. The switch to an APPROXIMATE
 * index (HNSW) is a separate later gate.
 */
class RetrieverResolver
{
    public function __construct(
        private readonly JsonbCosineRetriever $jsonb,
        private readonly PgvectorRetriever $pgvector,
    ) {
    }

    /** @param list<float> $queryEmbedding */
    public function forQuery(array $queryEmbedding): RetrieverInterface
    {
        if (config('cms.sumi.pgvector', false) && count($queryEmbedding) === RagChunkRecord::VECTOR_DIMS) {
            return $this->pgvector;
        }

        return $this->jsonb;
    }
}
