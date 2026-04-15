<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Theme extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'name', 'config', 'template_path', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
