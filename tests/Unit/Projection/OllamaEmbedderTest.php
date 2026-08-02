<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Rag\OllamaEmbedder;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OllamaEmbedderTest extends TestCase
{
    public function test_embeds_via_ollama_endpoint(): void
    {
        Http::fake([
            '10.10.0.2:11434/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);

        $embedder = new OllamaEmbedder('http://10.10.0.2:11434', 'bge-m3', 1024);

        $this->assertSame([0.1, 0.2, 0.3], $embedder->embed('some text'));
        $this->assertSame('ollama:bge-m3', $embedder->model());
        $this->assertSame(1024, $embedder->dimensions());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/embeddings')
            && $request['model'] === 'bge-m3'
            && $request['prompt'] === 'some text');
    }

    public function test_down_host_throws_clearly(): void
    {
        Http::fake(['10.10.0.2:11434/*' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);
        (new OllamaEmbedder('http://10.10.0.2:11434'))->embed('x');
    }

    public function test_empty_embedding_throws(): void
    {
        Http::fake(['*' => Http::response(['embedding' => []])]);

        $this->expectException(RuntimeException::class);
        (new OllamaEmbedder('http://127.0.0.1:11434'))->embed('x');
    }
}
