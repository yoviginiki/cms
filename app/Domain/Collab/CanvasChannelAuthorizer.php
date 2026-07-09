<?php

namespace App\Domain\Collab;

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes joining a canvas page's collaboration presence channel.
 *
 * Doubly gated: the page lookup runs under the tenant RLS GUC (set by
 * SetTenantFromAuth on the broadcasting-auth request), so a cross-tenant page is
 * simply invisible; and the `update` policy must allow the user. Returns the
 * safe presence member payload on success, or false to reject the join.
 *
 * Extracted from routes/channels.php so the security logic is unit-testable
 * without a running Reverb server or the pusher signing endpoint.
 */
class CanvasChannelAuthorizer
{
    /** @return array{id:string,name:string}|false */
    public function authorize(User $user, string $pageId): array|false
    {
        $page = Page::find($pageId); // tenant-scoped via RLS
        if ($page === null || Gate::forUser($user)->denies('update', $page)) {
            return false;
        }

        // Cursor/selection colors are derived client-side (colorForId) from the id,
        // so the presence payload carries no PII beyond the display name.
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
        ];
    }
}
