<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'category',
        'preview_image', 'template_data', 'page_count',
        'is_public', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'template_data' => 'array',
            'is_public' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
