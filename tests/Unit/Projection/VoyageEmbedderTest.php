<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Rag\VoyageEmbedder;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class VoyageEmbedderTest extends TestCase
{
    public function test_unconfigured_reports_and_throws(): void
    {
        $embedder = new VoyageEmbedder(apiKey: '');
        $this->assertFalse($embedder->isConfigured());

        $this->expectException(RuntimeException::class);
        $embedder->embed('hello');
    }

    public function test_embeds_via_voyage_api(): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::response([
                'data' => [['embedding' => [0.1, 0.2, 0.3]]],
            ]),
        ]);

        $embedder = new VoyageEmbedder(apiKey: 'test-key', model: 'voyage-3');
        $this->assertTrue($embedder->isConfigured());

        $vector = $embedder->embed('some content');
        $this->assertSame([0.1, 0.2, 0.3], $vector);
        $this->assertSame('voyage-3', $embedder->model());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'voyage-3'
            && $request['input'] === ['some content']);
    }

    public function test_api_failure_throws(): void
    {
        Http::fake(['api.voyageai.com/*' => Http::response(['error' => 'nope'], 500)]);

        $this->expectException(RuntimeException::class);
        (new VoyageEmbedder(apiKey: 'test-key'))->embed('x');
    }
}
