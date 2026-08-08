<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored RAG chunk (Sumi index). Embedding is a jsonb float array; similarity
 * is computed in PHP. Named *Record to avoid clashing with the pure value
 * object App\Domain\Projection\Rag\RagChunk.
 */
class RagChunkRecord extends Model
{
    use HasUuids;

    /**
     * Dimension of the additive pgvector column `embedding_vec`. Bound to the
     * production embedder (voyage-3 → 1024, see VoyageEmbedder::$dims). Only
     * embeddings of exactly this length are written to the vector column;
     * shorter offline (hash-16) rows stay jsonb-only and keep using PHP cosine,
     * so a mixed-dimension table remains valid.
     */
    public const VECTOR_DIMS = 1024;

    protected $table = 'rag_chunks';

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'page_id', 'page_version_id', 'segment_id', 'address',
        'heading_path', 'text', 'hash', 'embedding', 'model', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'heading_path' => 'array',
            'embedding' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * pgvector text literal for a float array, e.g. "[0.1,0.2,...]". Bind it as
     * a parameter and cast with `?::vector`; never interpolate into SQL.
     *
     * @param list<float> $embedding
     */
    public static function vectorLiteral(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($f) => (float) $f, $embedding)) . ']';
    }
}
