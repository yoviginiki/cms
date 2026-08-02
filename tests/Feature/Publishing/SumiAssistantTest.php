<?php

namespace Tests\Feature\Publishing;

use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\RagChunk;
use App\Domain\Projection\Rag\RagStore;
use App\Domain\Projection\Rag\Retrieval\RetrieverResolver;
use App\Domain\Projection\Rag\SumiAssistant;
use App\Models\RagChunkRecord;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SumiAssistantTest extends TestCase
{
    private function seedChunks(Site $site, HashEmbedder $embedder): void
    {
        foreach ([
            'a' => 'The projection is the single source of truth for machine-readable views.',
            'b' => 'Publishing produces flat static HTML with PageSpeed 100.',
        ] as $id => $text) {
            RagChunkRecord::create([
                'site_id' => $site->id, 'segment_id' => $id, 'address' => "{$id}#content",
                'heading_path' => ['Guide', 'Intro'], 'text' => $text, 'hash' => hash('sha256', $text),
                'embedding' => $embedder->embed($text), 'model' => $embedder->model(),
            ]);
        }
    }

    public function test_read_switch_routes_answer_through_pgvector_when_enabled(): void
    {
        config(['cms.sumi.pgvector' => true, 'cms.ai.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Grounded via pgvector. [a#content]']],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
            ]),
        ]);

        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);

        // 1024-dim embedder → RagStore dual-writes embedding_vec (switch is on).
        $embedder = new HashEmbedder(RagChunkRecord::VECTOR_DIMS);
        $text = 'The projection is the single source of truth.';
        app(RagStore::class)->store($site->id, [
            new RagChunk(
                id: 'a', pageId: '', pageVersionId: '', address: 'a#content',
                headingPath: ['Guide'], text: $text, hash: hash('sha256', $text),
                embedding: $embedder->embed($text), model: $embedder->model(),
            ),
        ]);

        // The vector column is populated → the pgvector path is eligible...
        $this->assertSame(1, RagChunkRecord::where('site_id', $site->id)->whereNotNull('embedding_vec')->count());
        // ...and the resolver actually routes a full-dim query to it.
        $qe = $embedder->embed('what is the projection');
        $this->assertSame('pgvector-exact', app(RetrieverResolver::class)->forQuery($qe)->name());

        $result = app(SumiAssistant::class)->answer($site->id, 'what is the projection', $embedder, 3);

        $this->assertStringContainsString('Grounded via pgvector', $result['answer']);
        $this->assertNotEmpty($result['sources']);
    }

    public function test_answers_from_retrieved_context_with_sources(): void
    {
        config(['cms.ai.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'The projection is the single source of truth. [a#content]']],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 8],
            ]),
        ]);

        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $embedder = new HashEmbedder(16);
        $this->seedChunks($site, $embedder);

        $result = app(SumiAssistant::class)->answer($site->id, 'What is the projection?', $embedder, 2);

        $this->assertStringContainsString('single source of truth', $result['answer']);
        $this->assertNotEmpty($result['sources']);
        $this->assertSame(20, $result['usage']['input']);

        // The model was given the retrieved passages as context.
        Http::assertSent(function ($request) {
            $body = json_encode($request->data());
            return str_contains($body, 'Context passages')
                && str_contains($body, 'single source of truth');
        });
    }

    public function test_no_index_returns_graceful_answer_without_calling_model(): void
    {
        config(['cms.ai.api_key' => 'test-key']);
        Http::fake();

        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);

        $result = app(SumiAssistant::class)->answer($site->id, 'anything?', new HashEmbedder(16), 3);

        $this->assertStringContainsString('no indexed content', $result['answer']);
        $this->assertSame([], $result['sources']);
        Http::assertNothingSent();
    }
}
