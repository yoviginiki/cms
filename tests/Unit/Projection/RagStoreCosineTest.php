<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Rag\RagStore;
use Tests\TestCase;

class RagStoreCosineTest extends TestCase
{
    private RagStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new RagStore();
    }

    public function test_identical_vectors_score_one(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->store->cosine([1, 2, 3], [1, 2, 3]), 1e-9);
    }

    public function test_orthogonal_vectors_score_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->store->cosine([1, 0], [0, 1]), 1e-9);
    }

    public function test_opposite_vectors_score_minus_one(): void
    {
        $this->assertEqualsWithDelta(-1.0, $this->store->cosine([1, 1], [-1, -1]), 1e-9);
    }

    public function test_empty_or_zero_vectors_score_zero(): void
    {
        $this->assertSame(0.0, $this->store->cosine([], [1, 2]));
        $this->assertSame(0.0, $this->store->cosine([0, 0], [1, 2]));
    }
}
