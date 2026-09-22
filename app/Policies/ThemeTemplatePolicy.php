<?php

namespace App\Policies;

use App\Domain\Concerns\AuthorizesWithTenant;
use App\Models\Site;
use App\Models\ThemeTemplate;
use App\Models\User;

/**
 * F07 (audit 2026-09-22): one policy for every theme-template operation,
 * including the blocks sync that previously only checked site ownership.
 * Templates shape every page/post that uses them, so writes are admin+.
 */
class ThemeTemplatePolicy
{
    use AuthorizesWithTenant;

    public function view(User $user, ThemeTemplate $template): bool
    {
        return $this->sameTenant($user, $template);
    }

    public function create(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $site);
    }

    public function update(User $user, ThemeTemplate $template): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $template);
    }

    public function delete(User $user, ThemeTemplate $template): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $template);
    }
}
