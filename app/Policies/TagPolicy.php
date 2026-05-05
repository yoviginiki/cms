<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    use AuthorizesWithTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->sameTenantViaSite($user, $tag);
    }

    public function create(User $user): bool
    {
        return $user->hasMinimumRole('editor');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenantViaSite($user, $tag);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenantViaSite($user, $tag);
    }

    private function sameTenantViaSite(User $user, Tag $tag): bool
    {
        return $tag->site && $tag->site->tenant_id === $user->tenant_id;
    }
}
