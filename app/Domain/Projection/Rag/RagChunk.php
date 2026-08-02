<?php

namespace App\Domain\Projection\Rag;

/**
 * One embedded, provenance-carrying chunk ready for the RAG index. A chunk is a
 * projection segment plus its embedding — the projection already did the
 * semantic chunking, so Sumi's indexing is a thin map over segments.
 */
final class RagChunk
{
    /**
     * @param list<string> $headingPath
     * @param list<float>  $embedding
     */
    public function __construct(
        public readonly string $id,
        public readonly string $pageId,
        public readonly string $pageVersionId,
        public readonly string $address,
        public readonly array $headingPath,
        public readonly string $text,
        public readonly string $hash,
        public readonly array $embedding,
        public readonly string $model,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->pageId,
            'page_version_id' => $this->pageVersionId,
            'address' => $this->address,
            'heading_path' => $this->headingPath,
            'text' => $this->text,
            'hash' => $this->hash,
            'embedding' => $this->embedding,
            'model' => $this->model,
        ];
    }
}
