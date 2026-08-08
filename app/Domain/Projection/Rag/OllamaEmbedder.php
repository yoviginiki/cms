<?php

namespace App\Domain\Projection\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Local embedding provider via Ollama (e.g. the Home PC on the WireGuard tunnel
 * running `bge-m3`, 1024-dim, multilingual incl. Bulgarian). No API key, no
 * per-token cost, and content never leaves the operator's own machines — the
 * best fit for this self-hosted, cost-conscious project.
 *
 * Talks to Ollama's `/api/embeddings` endpoint. Host/model/dimension come from
 * `cms.sumi.ollama.*`; the default host is the Home PC (10.10.0.2:11434). The
 * embedder is only usable while that host is reachable — embed() throws a clear
 * error otherwise, so a down machine surfaces loudly instead of silently
 * producing garbage.
 */
class OllamaEmbedder implements Embedder
{
    private string $baseUrl;
    private string $modelName;
    private int $dims;

    public function __construct(?string $baseUrl = null, ?string $model = null, ?int $dims = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('cms.sumi.ollama.base_url', 'http://10.10.0.2:11434'), '/');
        $this->modelName = $model ?? (string) config('cms.sumi.ollama.model', 'bge-m3');
        $this->dims = $dims ?? (int) config('cms.sumi.ollama.dims', 1024);
    }

    public function embed(string $text): array
    {
        $response = Http::timeout((int) config('cms.sumi.ollama.timeout', 30))
            ->acceptJson()
            ->post($this->baseUrl . '/api/embeddings', [
                'model' => $this->modelName,
                'prompt' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Ollama embed failed (HTTP {$response->status()}) at {$this->baseUrl} — is the host up and the model pulled?"
            );
        }

        $vector = $response->json('embedding');
        if (! is_array($vector) || $vector === []) {
            throw new RuntimeException("Ollama returned no embedding for model '{$this->modelName}'.");
        }

        return array_map('floatval', $vector);
    }

    public function dimensions(): int
    {
        return $this->dims;
    }

    public function model(): string
    {
        return 'ollama:' . $this->modelName;
    }
}
