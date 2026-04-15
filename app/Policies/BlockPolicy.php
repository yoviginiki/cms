<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Block;
use App\Models\User;

class BlockPolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Block $block): bool
    {
        return $this->sameTenant($user, $block);
    }

    public function create(User $user, Block $parentOrBlockable): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $parentOrBlockable);
    }

    public function update(User $user, Block $block): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $block);
    }

    public function delete(User $user, Block $block): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $block);
    }
}
