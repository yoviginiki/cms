<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;

class PagePolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return $this->sameTenant($user, $page);
    }

    public function create(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $page);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $page);
    }

    public function reorder(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }
}
