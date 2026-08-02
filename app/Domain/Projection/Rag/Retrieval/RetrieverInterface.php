<?php

namespace App\Domain\Projection\Rag\Retrieval;

use App\Models\RagChunkRecord;

/**
 * A ranked retriever over the Sumi index. The original jsonb PHP-cosine path
 * (JsonbCosineRetriever) and the pgvector path (PgvectorRetriever) are two
 * implementations of this one contract — nothing above the interface knows or
 * cares which backend answered. A future ranker can combine candidates from any
 * number of retrievers behind the same shape.
 */
interface RetrieverInterface
{
    /**
     * Top-K chunks most similar to a query embedding, best first.
     *
     * @param list<float> $queryEmbedding
     * @return list<array{chunk:RagChunkRecord,score:float}>
     */
    public function retrieve(string $siteId, array $queryEmbedding, int $topK = 5): array;

    /** Stable identifier of this retriever (for logging and parity comparison). */
    public function name(): string;
}
