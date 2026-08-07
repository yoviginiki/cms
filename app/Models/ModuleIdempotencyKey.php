<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per successfully-processed idempotent module request. See the
 * migration and docs decision IDEMPOTENCY.
 */
class ModuleIdempotencyKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'module_id',
        'tenant_id',
        'idempotency_key',
        'payload_hash',
        'external_id',
        'entity_type',
        'entity_id',
    ];
}
