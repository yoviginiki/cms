<?php

namespace App\Domain\Projection\Rag;

/**
 * Deterministic, dependency-free embedder for offline development and tests.
 *
 * NOT semantic — it derives a stable pseudo-vector from the text hash. It lets
 * the indexing pipeline be exercised end-to-end without an embedding API key.
 * The real provider (Voyage) replaces it in production.
 */
class HashEmbedder implements Embedder
{
    public function __construct(private readonly int $dims = 16)
    {
    }

    public function embed(string $text): array
    {
        $bytes = hash('sha256', $text, true); // 32 raw bytes
        $len = strlen($bytes);
        $out = [];
        for ($i = 0; $i < $this->dims; $i++) {
            $out[] = round(ord($bytes[$i % $len]) / 255, 6);
        }

        return $out;
    }

    public function dimensions(): int
    {
        return $this->dims;
    }

    public function model(): string
    {
        return 'hash-' . $this->dims;
    }
}
