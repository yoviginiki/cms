<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Site $site): bool
    {
        return $this->sameTenant($user, $site);
    }

    public function create(User $user): bool
    {
        return $user->hasMinimumRole('admin');
    }

    public function update(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->isOwner() && $this->sameTenant($user, $site);
    }

    public function publish(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }
}
