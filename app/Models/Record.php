<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A Record (Track G): one row of a Collection. Field values live in JSONB
 * `data`, shaped and sanitized by the collection schema at the service layer.
 * slug/title/status are denormalized real columns. `search_text` (pgsql
 * tsvector, not a fillable/cast column) is maintained by RecordService.
 */
class Record extends Model
{
    use HasUuids;

    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'collection_id', 'site_id', 'slug', 'title', 'status', 'position', 'data', 'published_at',
        'needs_republish', 'needs_republish_reason', 'publish_at', 'unpublish_at', 'seo_meta',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'seo_meta' => 'array',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ContentCollection::class, 'collection_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Outgoing relation edges (this record's relation fields). */
    public function relationsOut(): HasMany
    {
        return $this->hasMany(RecordRelation::class, 'from_record_id');
    }

    /** Incoming relation edges (other records pointing here). */
    public function relationsIn(): HasMany
    {
        return $this->hasMany(RecordRelation::class, 'to_record_id');
    }

    /**
     * Filter by a schema-field value inside JSONB data. Uses containment (@>)
     * on pgsql so the GIN jsonb_path_ops index applies; portable ->> fallback
     * elsewhere.
     */
    public function scopeWhereField(Builder $query, string $key, mixed $value): Builder
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->whereRaw('data @> ?', [json_encode([$key => $value])]);
        }

        return $query->where("data->{$key}", $value);
    }

    /** Rebuild search_text from the given strings (pgsql only; no-op elsewhere). */
    public function updateSearchText(array $strings): void
    {
        if ($this->getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $text = mb_substr(implode(' ', array_filter($strings, fn ($s) => is_string($s) && $s !== '')), 0, 100000);

        DB::update("UPDATE records SET search_text = to_tsvector('simple', ?) WHERE id = ?", [$text, $this->id]);
    }
}
