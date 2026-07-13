<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An edge between two Records for a schema field of type 'relation', keyed by
 * the field key on the FROM side. `pivot` carries typed fields living on the
 * relation itself (validated against the field's pivot_fields schema).
 */
class RecordRelation extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'from_record_id', 'to_record_id', 'relation_key', 'pivot', 'position',
    ];

    protected function casts(): array
    {
        return [
            'pivot' => 'array',
        ];
    }

    public function fromRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'from_record_id');
    }

    public function toRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'to_record_id');
    }
}
