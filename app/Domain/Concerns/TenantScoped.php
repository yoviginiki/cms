<?php

namespace App\Domain\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Application-level tenant isolation.
 * Works on both PostgreSQL (second layer after RLS) and MySQL (primary isolation).
 *
 * Apply to models that have a tenant_id column directly.
 */
trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        // Global scope: auto-filter by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::resolveCurrentTenantId();
            if ($tenantId) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Auto-fill tenant_id on creating
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = static::resolveCurrentTenantId();
            }
        });
    }

    protected static function resolveCurrentTenantId(): ?string
    {
        $user = Auth::user();
        return $user?->tenant_id;
    }
}
