<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Category;
use App\Models\Site;
use App\Models\User;

class CategoryPolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $this->sameTenant($user, $category);
    }

    public function create(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $category);
    }
}
