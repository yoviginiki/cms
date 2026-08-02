<?php

namespace App\Domain\Projection\Rag;

use App\Domain\Projection\Projection;

/**
 * Sumi (RAG) indexing pipeline — first slice. Maps a projection's segments to
 * embedded, provenance-carrying chunks. The projection already produced
 * semantically-bounded segments with heading paths and stable hashes, so this
 * is a thin, deterministic map (given a deterministic embedder).
 *
 * The per-segment hash enables incremental reindexing: when one block changes,
 * only its chunk's hash changes, so only that chunk needs re-embedding.
 */
class RagIndexer
{
    public function __construct(private readonly Embedder $embedder)
    {
    }

    /** @return list<RagChunk> */
    public function index(Projection $projection): array
    {
        $data = $projection->toArray();
        $source = $data['source'];

        $chunks = [];
        foreach ($data['segments'] as $segment) {
            $chunks[] = new RagChunk(
                id: $segment['id'],
                pageId: (string) $source['page_id'],
                pageVersionId: (string) $source['page_version_id'],
                address: $segment['address'],
                headingPath: $segment['heading_path'],
                text: $segment['text'],
                hash: $segment['hash'],
                embedding: $this->embedder->embed($segment['text']),
                model: $this->embedder->model(),
            );
        }

        return $chunks;
    }

    /**
     * Incremental reindex: return only the chunks whose hash is not already
     * known, so an unchanged block never gets re-embedded.
     *
     * @param array<string,true> $knownHashes hash => true
     * @return list<RagChunk>
     */
    public function indexChanged(Projection $projection, array $knownHashes): array
    {
        return array_values(array_filter(
            $this->index($projection),
            fn (RagChunk $chunk) => ! isset($knownHashes[$chunk->hash]),
        ));
    }
}
