<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-global module catalogue entry. See the modules migration and the
 * ModuleRegistry service for the enablement resolution rule.
 */
class Module extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled_globally',
        'settings_schema',
    ];

    protected $casts = [
        'enabled_globally' => 'boolean',
        'settings_schema' => 'array',
    ];

    public function tenantPivots(): HasMany
    {
        return $this->hasMany(ModuleTenant::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ModuleToken::class);
    }
}
