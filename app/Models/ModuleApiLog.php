<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit record for a token-authenticated module request. See the migration and
 * docs decision AUDIT.
 */
class ModuleApiLog extends Model
{
    use HasUuids;

    public const GRANTED = 'granted';
    public const DENIED_AUTH = 'denied_auth';
    public const DENIED_ABILITY = 'denied_ability';
    public const DENIED_MODULE_DISABLED = 'denied_module_disabled';

    protected $fillable = [
        'module_id',
        'module_token_id',
        'tenant_id',
        'method',
        'path',
        'ability',
        'decision',
        'status_code',
        'ip_address',
    ];

    protected $casts = [
        'status_code' => 'integer',
    ];
}
