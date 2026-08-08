<?php

namespace App\Domain\Projection\Rag;

/**
 * Selects the embedding provider from `cms.sumi.embedder`:
 *   - voyage → {@see VoyageEmbedder}  (hosted API, needs services.voyage.key)
 *   - ollama → {@see OllamaEmbedder}  (local, e.g. Home PC bge-m3, no key/cost)
 *   - hash   → {@see HashEmbedder}    (offline, non-semantic — dev/tests only)
 *   - auto   → Voyage if a key is set, else hash (backward-compatible default)
 *
 * Both provider paths stay in the codebase; the operator switches between them
 * in settings. Retrieval dimension follows the chosen model (Voyage-3 and bge-m3
 * are both 1024 → they populate the vector column; hash-16 stays jsonb-only).
 */
class EmbedderFactory
{
    public function make(): Embedder
    {
        return match (strtolower((string) config('cms.sumi.embedder', 'auto'))) {
            'voyage' => new VoyageEmbedder(),
            'ollama' => new OllamaEmbedder(),
            'hash' => new HashEmbedder((int) config('cms.sumi.hash_dims', 16)),
            default => config('services.voyage.key')
                ? new VoyageEmbedder()
                : new HashEmbedder((int) config('cms.sumi.hash_dims', 16)),
        };
    }
}
