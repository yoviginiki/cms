<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;

class PostPolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $this->sameTenant($user, $post);
    }

    public function create(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $post);
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $post);
    }

    public function reorder(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }
}
