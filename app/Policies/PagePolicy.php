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

    /**
     * Open inline edit mode over the preview and save inline field patches.
     * editor+ only — viewer and author cannot inline-edit.
     *
     * NOTE: brief 4.3 wants author to inline-edit their OWN pages; that needs a
     * pages.author_id ownership column that does not exist yet, so the minimal
     * additive extension keeps inline edit at editor+. Add ownership + an author
     * branch here to enable author-scoped editing later.
     */
    public function inlineEdit(User $user, Page $page): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $page);
    }

    /**
     * Publish from inline edit mode. admin+ only — an editor can inline-edit a
     * draft but not publish it (brief 4.3).
     */
    public function inlinePublish(User $user, Page $page): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $page);
    }

    public function reorder(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }
}
