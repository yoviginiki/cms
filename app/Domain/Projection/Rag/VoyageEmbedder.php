<?php

namespace App\Domain\Projection\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real embedding provider (Voyage). Anthropic has no embeddings model, so the
 * projection layer uses Voyage — Anthropic's recommended partner. Behind an API
 * key (`services.voyage.key` ← VOYAGE_API_KEY); until it is set, the pipeline
 * runs on {@see HashEmbedder}.
 */
class VoyageEmbedder implements Embedder
{
    private string $apiKey;

    public function __construct(
        ?string $apiKey = null,
        private readonly string $model = 'voyage-3',
        private readonly int $dims = 1024,
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.voyage.key', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function embed(string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Voyage is not configured (VOYAGE_API_KEY missing).');
        }

        $response = Http::withToken($this->apiKey)
            ->asJson()
            ->post('https://api.voyageai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => [$text],
                'input_type' => 'document',
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Voyage embed failed (HTTP {$response->status()}).");
        }

        $vector = $response->json('data.0.embedding');
        if (! is_array($vector)) {
            throw new RuntimeException('Voyage returned no embedding.');
        }

        return array_map('floatval', $vector);
    }

    public function dimensions(): int
    {
        return $this->dims;
    }

    public function model(): string
    {
        return $this->model;
    }
}
