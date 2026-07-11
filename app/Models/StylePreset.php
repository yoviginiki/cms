<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Style Preset (Builder Experience P3). A named style bundle a block links to
 * (block.preset_id + local overrides), so editing the preset restyles every
 * linked block. `is_system` is deliberately NOT fillable — shared system
 * presets (site_id NULL) are seeded by a privileged path, never forged through
 * the app connection (the RLS WITH CHECK also blocks NULL-site writes).
 */
class StylePreset extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'block_type', 'kind', 'group', 'name', 'slug', 'style', 'is_default', 'sort',
    ];

    public const KINDS = ['element', 'group'];

    /** Option groups a kind=group preset may scope to. */
    public const GROUPS = ['spacing', 'typography', 'border', 'color'];

    protected function casts(): array
    {
        return [
            'style' => 'array',
            'is_default' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
