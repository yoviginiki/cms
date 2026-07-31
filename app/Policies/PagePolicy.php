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
     * editor+ on any page; an author only on pages they created; viewer never.
     *
     * Author ownership uses pages.author_id (Page::author). That column is null
     * until the add-author_id-to-pages migration has run and been backfilled, so
     * authors stay locked out until then — safe to ship ahead of the migration.
     */
    public function inlineEdit(User $user, Page $page): bool
    {
        if (!$this->sameTenant($user, $page)) {
            return false;
        }

        if ($user->hasMinimumRole('editor')) {
            return true;
        }

        return $user->role === 'author'
            && $page->author_id !== null
            && $page->author_id === $user->id;
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
