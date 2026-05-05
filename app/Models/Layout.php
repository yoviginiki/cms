<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Layout extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'parent_layout_id', 'slug', 'name', 'description',
        'wrapper_blade_view', 'supports', 'allowed_block_types',
        'promoted_block_types', 'default_block_stack', 'assets', 'config',
        'is_system', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'supports' => 'array',
            'allowed_block_types' => 'array',
            'promoted_block_types' => 'array',
            'default_block_stack' => 'array',
            'assets' => 'array',
            'config' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Layout::class, 'parent_layout_id');
    }

    protected static function booted(): void
    {
        static::updating(function (Layout $layout) {
            if ($layout->is_system && $layout->getOriginal('is_system')) {
                throw new \DomainException('System layouts cannot be edited. Fork the layout to customize it.');
            }
        });

        static::deleting(function (Layout $layout) {
            if ($layout->is_system) {
                throw new \DomainException('System layouts cannot be deleted.');
            }
        });
    }
}
