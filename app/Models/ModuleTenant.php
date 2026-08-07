<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant enablement + settings for a module (table `module_tenant`).
 * Tenant-RLS'd; only ever read/written inside an authenticated tenant context.
 */
class ModuleTenant extends Model
{
    use HasUuids;

    protected $table = 'module_tenant';

    protected $fillable = [
        'module_id',
        'tenant_id',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
