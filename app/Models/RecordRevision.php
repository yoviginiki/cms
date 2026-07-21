<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time snapshot of a record, written on every save/delete/restore.
 * Immutable append-only log; pruned to the most recent per-record window by
 * RecordRevisionService.
 */
class RecordRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'site_id', 'record_id', 'event', 'title', 'slug', 'status',
        'data', 'relations', 'user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'relations' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
