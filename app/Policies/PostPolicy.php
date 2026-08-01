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

    /**
     * Inline edit over the preview: editor+ on any post; an author only on posts
     * they own (posts.author_id); viewer never. Mirrors PagePolicy::inlineEdit.
     */
    public function inlineEdit(User $user, Post $post): bool
    {
        if (!$this->sameTenant($user, $post)) {
            return false;
        }

        if ($user->hasMinimumRole('editor')) {
            return true;
        }

        return $user->role === 'author'
            && $post->author_id !== null
            && $post->author_id === $user->id;
    }

    /** Publish from inline edit mode — admin+ only (editor cannot). */
    public function inlinePublish(User $user, Post $post): bool
    {
        return $user->hasMinimumRole('admin') && $this->sameTenant($user, $post);
    }

    public function reorder(User $user, Site $site): bool
    {
        return $user->hasMinimumRole('editor') && $this->sameTenant($user, $site);
    }
}
