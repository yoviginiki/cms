<?php

use App\Domain\Collab\CanvasChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

/*
 * Presence channel for collaborative canvas editing. The join is authorized by
 * CanvasChannelAuthorizer (tenant RLS + `update` policy); a truthy array return
 * makes the user a presence member with {id, name, color}. The tenant GUC is set
 * by SetTenantFromAuth on the broadcasting-auth request (see bootstrap/app.php).
 */
Broadcast::channel('canvas.page.{pageId}', function ($user, string $pageId) {
    return app(CanvasChannelAuthorizer::class)->authorize($user, $pageId);
}, ['guard' => 'web']);
