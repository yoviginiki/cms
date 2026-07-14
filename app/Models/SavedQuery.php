<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Saved Query (Track G-Q): a reusable, validated query over collection
 * records — authored visually (mode 'simple', `definition` JSON) or as
 * guarded SQL (mode 'sql', G-Q2). Runs only through QueryRunner; never
 * trusted raw.
 */
class SavedQuery extends Model
{
    use HasUuids;

    public const MODES = ['simple', 'sql'];

    public const PARAM_TYPES = ['text', 'number', 'boolean', 'select'];

    protected $fillable = [
        'site_id', 'name', 'slug', 'mode', 'definition', 'sql',
        'public_params', 'is_public', 'settings', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'public_params' => 'array',
            'is_public' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sourceCollection(): ?ContentCollection
    {
        $id = $this->definition['collection_id'] ?? null;

        return $id ? ContentCollection::find($id) : null;
    }
}
