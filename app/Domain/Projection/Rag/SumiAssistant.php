<?php

namespace App\Domain\Projection\Rag;

use App\Domain\Projection\Rag\Retrieval\RetrieverResolver;
use App\Services\AI\AnthropicClient;

/**
 * Sumi (RAG) — the answer step. Retrieves the most relevant projection chunks
 * for a question and asks the model to answer using ONLY that context, citing
 * chunk addresses. Grounded, provenance-carrying answers over the site content.
 *
 * Retrieval goes through {@see RetrieverResolver}, which picks the jsonb or
 * pgvector backend behind the `cms.sumi.pgvector` switch — the answer step is
 * agnostic to which one answered.
 */
class SumiAssistant
{
    public function __construct(
        private readonly RetrieverResolver $retrievers,
        private readonly AnthropicClient $ai,
    ) {
    }

    /**
     * @return array{answer:string,sources:list<array{address:string,heading_path:list<string>,page_id:string,score:float}>,usage:?array}
     */
    public function answer(string $siteId, string $question, Embedder $embedder, int $topK = 5, ?string $model = null): array
    {
        $queryEmbedding = $embedder->embed($question);
        $hits = $this->retrievers->forQuery($queryEmbedding)->retrieve($siteId, $queryEmbedding, $topK);

        if ($hits === []) {
            return [
                'answer' => "There is no indexed content for this site yet, so I can't answer that.",
                'sources' => [],
                'usage' => null,
            ];
        }

        $system = [[
            'type' => 'text',
            'text' => 'You are Sumi, an assistant that answers questions about this website using ONLY the '
                . 'provided context passages. Cite the passages you use by their [address]. If the context does '
                . 'not contain the answer, say you do not know — never invent facts or use outside knowledge.',
        ]];
        $messages = [[
            'role' => 'user',
            'content' => "Context passages:\n\n" . $this->buildContext($hits) . "\n\nQuestion: {$question}",
        ]];

        $result = $this->ai->complete($model ?? (string) config('cms.ai.model'), $system, $messages, 1024);

        return [
            'answer' => $result['text'],
            'sources' => $this->sources($hits),
            'usage' => $result['usage'] ?? null,
        ];
    }

    /** @param list<array{chunk:RagChunkRecord,score:float}> $hits */
    private function buildContext(array $hits): string
    {
        $blocks = [];
        foreach ($hits as $hit) {
            $chunk = $hit['chunk'];
            $trail = $chunk->heading_path ? ' (under: ' . implode(' › ', $chunk->heading_path) . ')' : '';
            $blocks[] = "[{$chunk->address}]{$trail}\n{$chunk->text}";
        }

        return implode("\n\n", $blocks);
    }

    /** @param list<array{chunk:RagChunkRecord,score:float}> $hits */
    private function sources(array $hits): array
    {
        return array_map(fn (array $hit) => [
            'address' => $hit['chunk']->address,
            'heading_path' => $hit['chunk']->heading_path ?? [],
            'page_id' => (string) $hit['chunk']->page_id,
            'score' => round($hit['score'], 6),
        ], $hits);
    }
}
