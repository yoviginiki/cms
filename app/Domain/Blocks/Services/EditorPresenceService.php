<?php

namespace App\Domain\Blocks\Services;

use App\Models\ActiveEditor;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;

class EditorPresenceService
{
    /**
     * Register or update heartbeat for a user editing content.
     */
    public function heartbeat(User $user, Page|Post $content): void
    {
        $isPage = $content instanceof Page;

        ActiveEditor::updateOrCreate(
            [
                'user_id' => $user->id,
                'page_id' => $isPage ? $content->id : null,
                'post_id' => $isPage ? null : $content->id,
            ],
            ['last_heartbeat' => now()]
        );
    }

    /**
     * Remove user from active editors on page leave.
     */
    public function leave(User $user, Page|Post $content): void
    {
        $isPage = $content instanceof Page;

        ActiveEditor::where('user_id', $user->id)
            ->where($isPage ? 'page_id' : 'post_id', $content->id)
            ->delete();
    }

    /**
     * Get active editors for a piece of content (heartbeat within last 60s).
     */
    public function getActiveEditors(Page|Post $content, ?string $excludeUserId = null): Collection
    {
        $isPage = $content instanceof Page;
        $field = $isPage ? 'page_id' : 'post_id';

        $query = ActiveEditor::with('user:id,name,email')
            ->where($field, $content->id)
            ->where('last_heartbeat', '>', now()->subSeconds(60));

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        return $query->get()->map(fn($ae) => [
            'id' => $ae->user->id,
            'name' => $ae->user->name,
            'email' => $ae->user->email,
            'since' => $ae->created_at,
        ]);
    }

    /**
     * Clean up stale heartbeats older than 2 minutes.
     */
    public function cleanup(): void
    {
        ActiveEditor::where('last_heartbeat', '<', now()->subMinutes(2))->delete();
    }
}
