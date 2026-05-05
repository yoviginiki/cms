<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grid extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'name', 'slug', 'description',
        'col_tracks', 'row_tracks', 'areas',
        'gap_x', 'gap_y', 'container_width', 'container_padding',
        'min_height', 'align_items', 'justify_items',
        'overflow_x', 'layout_mode', 'background_json', 'full_bleed',
        'is_preset', 'breakpoints_json',
    ];

    protected function casts(): array
    {
        return [
            'breakpoints_json' => 'array',
            'background_json' => 'array',
            'is_preset' => 'boolean',
            'full_bleed' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(GridPosition::class)->orderBy('mobile_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GridAssignment::class);
    }

    /**
     * Extract area names from the areas string.
     */
    public function getAreaNames(): array
    {
        preg_match_all('/"([^"]+)"/', $this->areas, $matches);
        $names = [];
        foreach ($matches[1] as $row) {
            foreach (explode(' ', trim($row)) as $name) {
                $name = trim($name);
                if ($name && $name !== '.' && !in_array($name, $names)) {
                    $names[] = $name;
                }
            }
        }
        return $names;
    }
}
