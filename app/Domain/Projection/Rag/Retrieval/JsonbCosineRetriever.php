<?php

namespace App\Domain\Projection\Rag\Retrieval;

use App\Domain\Projection\Rag\RagStore;

/**
 * The original retrieval path: a full per-site scan with cosine computed in PHP
 * over the jsonb embeddings. Behaviour is unchanged — it simply wraps
 * RagStore::search so the old path lives behind RetrieverInterface while the
 * pgvector path is validated alongside it. This is the "старата остава" side of
 * the parallel period.
 */
class JsonbCosineRetriever implements RetrieverInterface
{
    public function __construct(private readonly RagStore $store)
    {
    }

    public function retrieve(string $siteId, array $queryEmbedding, int $topK = 5): array
    {
        return $this->store->search($siteId, $queryEmbedding, $topK);
    }

    public function name(): string
    {
        return 'jsonb-cosine';
    }
}
