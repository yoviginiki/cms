<?php

namespace App\Providers;

use App\Http\Middleware\SetTenantFromAuth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The admin SPA authenticates by session cookie, so the broadcasting-auth
        // route uses the web group (session + CSRF); SetTenantFromAuth then sets
        // the RLS GUC so the channel callback's page lookup is tenant-scoped.
        Broadcast::routes(['middleware' => ['web', SetTenantFromAuth::class]]);

        require base_path('routes/channels.php');
    }
}
