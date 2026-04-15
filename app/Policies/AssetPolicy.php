<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Asset;
use App\Models\Site;
use App\Models\User;

class AssetPolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->sameTenant($user, $asset);
    }

    public function upload(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $asset);
    }
}
