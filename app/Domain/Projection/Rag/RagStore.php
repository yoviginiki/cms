<?php

namespace App\Domain\Projection\Rag;

use App\Models\RagChunkRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistence + retrieval for the RAG index. Stores chunks (incremental by
 * content hash) and answers similarity queries with cosine over the jsonb
 * embeddings. pgvector would replace the PHP cosine + add an ANN index; the
 * interface stays the same.
 */
class RagStore
{
    /** Cached existence of the additive pgvector column (null = not yet checked). */
    private ?bool $hasVectorColumn = null;

    /**
     * Store chunks for a site, skipping any whose hash is already indexed
     * (incremental reindex). Returns the number of new rows written.
     *
     * @param list<RagChunk> $chunks
     */
    public function store(string $siteId, array $chunks): int
    {
        if ($chunks === []) {
            return 0;
        }

        $known = RagChunkRecord::where('site_id', $siteId)
            ->whereIn('hash', array_map(fn (RagChunk $c) => $c->hash, $chunks))
            ->pluck('hash')
            ->flip();

        $written = 0;
        foreach ($chunks as $chunk) {
            if ($known->has($chunk->hash)) {
                continue;
            }
            $record = RagChunkRecord::create([
                'site_id' => $siteId,
                'page_id' => $chunk->pageId ?: null,
                'page_version_id' => $chunk->pageVersionId ?: null,
                'segment_id' => $chunk->id,
                'address' => $chunk->address,
                'heading_path' => $chunk->headingPath,
                'text' => $chunk->text,
                'hash' => $chunk->hash,
                'embedding' => $chunk->embedding,
                'model' => $chunk->model,
            ]);
            $this->writeVector((string) $record->id, $chunk->embedding);
            $written++;
        }

        return $written;
    }

    /**
     * Dual-write the embedding into the additive pgvector column, alongside the
     * jsonb. Only when its dimension matches the vector(N) column and the column
     * exists: offline hash-16 embeddings are shorter than the production 1024-dim
     * column and stay jsonb-only, and the column may be absent on a host where the
     * (additive, hand-run) pgvector migration hasn't been applied yet — indexing
     * must not break there. The jsonb path is unaffected in every case.
     *
     * Gated by the `cms.sumi.pgvector` kill-switch (default OFF): when off the
     * vector path is fully inert regardless of column/index presence, so a host
     * carries the schema with zero added write load until the operator opts in.
     *
     * @param list<float> $embedding
     */
    private function writeVector(string $id, array $embedding): void
    {
        if (! config('cms.sumi.pgvector', false)) {
            return;
        }
        if (count($embedding) !== RagChunkRecord::VECTOR_DIMS) {
            return;
        }
        if (! $this->hasVectorColumn()) {
            return;
        }

        DB::update(
            'UPDATE rag_chunks SET embedding_vec = ?::vector WHERE id = ?',
            [RagChunkRecord::vectorLiteral($embedding), $id],
        );
    }

    private function hasVectorColumn(): bool
    {
        return $this->hasVectorColumn ??= Schema::hasColumn('rag_chunks', 'embedding_vec');
    }

    /**
     * Top-K most similar chunks to a query embedding (cosine).
     *
     * @param list<float> $queryEmbedding
     * @return list<array{chunk:RagChunkRecord,score:float}> sorted by score desc
     */
    public function search(string $siteId, array $queryEmbedding, int $topK = 5): array
    {
        $scored = [];
        foreach (RagChunkRecord::where('site_id', $siteId)->get() as $record) {
            $scored[] = [
                'chunk' => $record,
                'score' => $this->cosine($queryEmbedding, $record->embedding ?? []),
            ];
        }

        // Deterministic order: score desc, then segment id asc as a tiebreak.
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score']
                ?: strcmp($a['chunk']->segment_id, $b['chunk']->segment_id);
        });

        return array_slice($scored, 0, max(0, $topK));
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    public function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $magA += $x * $x;
            $magB += $y * $y;
        }
        if ($magA <= 0.0 || $magB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
