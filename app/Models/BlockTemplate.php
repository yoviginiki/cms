<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable Library item (section / row / block-composition / single module).
 * Physically the `block_templates` table — extended in Builder Experience P1.
 *
 * The `id` column is a uuid with no DB default, so HasUuids is required for
 * create() to work at all (without it Eloquent inserts a NULL id → PK
 * violation). `is_system` is deliberately NOT fillable — a system/global item
 * (site_id = NULL) can only be created by a privileged seeder, never forged
 * through the app connection (the RLS WITH CHECK also blocks NULL-site writes).
 */
class BlockTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'name', 'slug', 'category', 'kind', 'tags', 'description', 'blocks_data', 'preview_image',
    ];

    /** Structural granularity of a library item. */
    public const KINDS = ['section', 'row', 'block-composition', 'module'];

    protected function casts(): array
    {
        return [
            'blocks_data' => 'array',
            'tags' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
