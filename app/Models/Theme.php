<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Theme extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    // NOTE: `is_system` is deliberately NOT fillable. A system theme
    // (site_id NULL, is_system true) is visible to every tenant, so it must
    // never be settable from request-driven mass assignment — only seeders set
    // it explicitly via forceFill/setAttribute. This is the app-side half of
    // the RLS WITH CHECK guard added in the 2026_07_09 themes-rls migration.
    protected $fillable = [
        'site_id', 'name', 'slug', 'version', 'description',
        'manifest_json', 'config', 'template_path',
        'document', 'modes', 'schema_version',
        'is_active', 'parent_theme_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'manifest_json' => 'array',
            'document' => 'array',
            'modes' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'parent_theme_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ThemeAssignment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ThemeVersion::class);
    }
}
