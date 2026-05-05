<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazinePage extends Model
{
    use HasUuids;

    protected $fillable = [
        'magazine_id', 'title', 'sort_order',
        'background_color', 'background_image', 'background_size', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function magazine(): BelongsTo
    {
        return $this->belongsTo(Magazine::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(MagazineElement::class)->orderBy('z_index');
    }
}
