<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Rag\Retrieval\RetrieverResolver;
use App\Models\RagChunkRecord;
use Tests\TestCase;

class RetrieverResolverTest extends TestCase
{
    private function resolver(): RetrieverResolver
    {
        return app(RetrieverResolver::class);
    }

    /** @return list<float> */
    private function vec(int $n): array
    {
        return array_fill(0, $n, 0.1);
    }

    public function test_switch_off_always_uses_jsonb(): void
    {
        config(['cms.sumi.pgvector' => false]);
        $this->assertSame('jsonb-cosine', $this->resolver()->forQuery($this->vec(RagChunkRecord::VECTOR_DIMS))->name());
    }

    public function test_switch_on_with_matching_dim_uses_pgvector(): void
    {
        config(['cms.sumi.pgvector' => true]);
        $this->assertSame('pgvector-exact', $this->resolver()->forQuery($this->vec(RagChunkRecord::VECTOR_DIMS))->name());
    }

    public function test_switch_on_but_short_dim_stays_on_jsonb(): void
    {
        // Offline hash-16 vectors were never written to the vector column, so a
        // 16-dim query must not read the (empty for it) pgvector path.
        config(['cms.sumi.pgvector' => true]);
        $this->assertSame('jsonb-cosine', $this->resolver()->forQuery($this->vec(16))->name());
    }
}
