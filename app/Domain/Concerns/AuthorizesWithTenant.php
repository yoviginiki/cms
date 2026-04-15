<?php

namespace App\Domain\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesWithTenant
{
    protected function sameTenant(User $user, Model $model): bool
    {
        $tenantId = $this->resolveTenantId($model);

        return $tenantId && $user->tenant_id === $tenantId;
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
