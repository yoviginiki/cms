<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Rag\EmbedderFactory;
use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\OllamaEmbedder;
use App\Domain\Projection\Rag\VoyageEmbedder;
use Tests\TestCase;

class EmbedderFactoryTest extends TestCase
{
    private function make(): object
    {
        return app(EmbedderFactory::class)->make();
    }

    public function test_explicit_ollama(): void
    {
        config(['cms.sumi.embedder' => 'ollama']);
        $this->assertInstanceOf(OllamaEmbedder::class, $this->make());
    }

    public function test_explicit_voyage(): void
    {
        config(['cms.sumi.embedder' => 'voyage']);
        $this->assertInstanceOf(VoyageEmbedder::class, $this->make());
    }

    public function test_explicit_hash_respects_dims(): void
    {
        config(['cms.sumi.embedder' => 'hash', 'cms.sumi.hash_dims' => 32]);
        $embedder = $this->make();
        $this->assertInstanceOf(HashEmbedder::class, $embedder);
        $this->assertSame(32, $embedder->dimensions());
    }

    public function test_auto_uses_hash_without_voyage_key(): void
    {
        config(['cms.sumi.embedder' => 'auto', 'services.voyage.key' => null]);
        $this->assertInstanceOf(HashEmbedder::class, $this->make());
    }

    public function test_auto_uses_voyage_when_key_present(): void
    {
        config(['cms.sumi.embedder' => 'auto', 'services.voyage.key' => 'pa-test']);
        $this->assertInstanceOf(VoyageEmbedder::class, $this->make());
    }
}
