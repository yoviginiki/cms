<?php

namespace App\Domain\Projection\Rag\Retrieval;

use App\Models\RagChunkRecord;

/**
 * pgvector retrieval: cosine distance in SQL via the `<=>` operator on the
 * additive `embedding_vec vector(N)` column.
 *
 * EXACT distance (no ANN index yet): its top-K is provably equivalent to the
 * jsonb PHP-cosine path, which the parity test asserts before any read is
 * switched over. An HNSW index is approximate and would make that exact
 * comparison meaningless, so it is a later, separate gate.
 *
 * Score is cosine similarity (1 - cosine distance) to match RagStore::cosine, so
 * the two retrievers are directly comparable. Rows without a populated
 * `embedding_vec` (e.g. offline hash-16 rows) are skipped — they belong to the
 * jsonb path. Tiebreak by segment_id asc, identical to RagStore::search, for a
 * deterministic order. RLS on rag_chunks scopes every row to the current tenant,
 * exactly as the jsonb path relies on.
 */
class PgvectorRetriever implements RetrieverInterface
{
    public function retrieve(string $siteId, array $queryEmbedding, int $topK = 5): array
    {
        if ($queryEmbedding === [] || $topK <= 0) {
            return [];
        }

        $vec = RagChunkRecord::vectorLiteral($queryEmbedding);

        $rows = RagChunkRecord::query()
            ->where('site_id', $siteId)
            ->whereNotNull('embedding_vec')
            ->selectRaw('rag_chunks.*, (embedding_vec <=> ?::vector) as cosine_distance', [$vec])
            ->orderByRaw('embedding_vec <=> ?::vector', [$vec])
            ->orderBy('segment_id')
            ->limit($topK)
            ->get();

        return array_map(static fn (RagChunkRecord $record) => [
            'chunk' => $record,
            'score' => 1.0 - (float) $record->cosine_distance,
        ], $rows->all());
    }

    public function name(): string
    {
        return 'pgvector-exact';
    }
}
