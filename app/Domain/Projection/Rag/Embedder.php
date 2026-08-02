<?php

namespace App\Domain\Projection\Rag;

/**
 * Pluggable embedding provider. The pipeline is written against this contract
 * so it can be built and tested offline (HashEmbedder) and swapped for a real
 * provider (e.g. Voyage) behind an API key without touching the pipeline.
 */
interface Embedder
{
    /** @return list<float> the embedding vector for $text */
    public function embed(string $text): array;

    public function dimensions(): int;

    /** Stable identifier of the model, stored with each chunk for reindexing. */
    public function model(): string;
}
