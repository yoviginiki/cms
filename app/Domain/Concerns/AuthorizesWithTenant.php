<?php

namespace App\Domain\Concerns;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesWithTenant
{
    protected function sameTenant(User $user, Model $model): bool
    {
        $tenantId = $this->resolveTenantId($model);

        if (!$tenantId || $user->tenant_id !== $tenantId) {
            return false;
        }

        // Per-site access: a user restricted to some sites must not reach
        // content of the others, even through a mismatched URL.
        if ($user->isRestrictedToSites()) {
            $siteId = $this->resolveSiteId($model);

            return $siteId !== null && $user->canAccessSite($siteId);
        }

        return true;
    }

    protected function resolveSiteId(Model $model): ?string
    {
        if ($model instanceof Site) {
            return $model->getKey();
        }

        if (isset($model->site_id)) {
            return $model->site_id;
        }

        if (method_exists($model, 'blockable') && $model->blockable) {
            return $this->resolveSiteId($model->blockable);
        }

        return null;
    }

    protected function resolveTenantId(Model $model): ?string
    {
        // Direct tenant_id on model
        if (isset($model->tenant_id)) {
            return $model->tenant_id;
        }

        // Through site relationship
        if (method_exists($model, 'site') && $model->site) {
            return $model->site->tenant_id;
        }

        // Through blockable (page/post) -> site
        if (method_exists($model, 'blockable') && $model->blockable) {
            return $this->resolveTenantId($model->blockable);
        }

        return null;
    }
}
