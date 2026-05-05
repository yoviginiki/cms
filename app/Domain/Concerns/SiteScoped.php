<?php

namespace App\Domain\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Application-level tenant isolation via site_id.
 * For models that belong to Site rather than directly to Tenant.
 * Scopes through site → tenant relationship.
 */
trait SiteScoped
{
    public static function bootSiteScoped(): void
    {
        static::addGlobalScope('site_tenant', function (Builder $builder) {
            $tenantId = static::resolveCurrentTenantIdFromAuth();
            if ($tenantId) {
                $builder->whereHas('site', function (Builder $q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                });
            }
        });
    }

    protected static function resolveCurrentTenantIdFromAuth(): ?string
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user?->tenant_id;
    }
}
