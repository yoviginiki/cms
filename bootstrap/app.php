<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Module receiving endpoints live at /api/modules/* (outside api/v1),
            // authenticated by module token rather than the user session.
            \Illuminate\Support\Facades\Route::prefix('api/modules')
                ->group(__DIR__.'/../routes/modules.php');
        },
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'tenant.scope' => \App\Http\Middleware\TenantScope::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'module.token' => \App\Http\Middleware\AuthModuleToken::class,
            'public.site' => \App\Http\Middleware\SetTenantFromPublicSite::class,
            'public.cors' => \App\Http\Middleware\PublicSiteCors::class,
        ]);

        // Public routes resolve {site} without auth: the tenant GUC must be
        // set BEFORE implicit binding or RLS hides the sites row (404).
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTenantFromPublicSite::class,
        );

        $middleware->statefulApi();

        // Replace API group: Sanctum → SetTenantFromAuth → SubstituteBindings
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SetTenantFromAuth::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect('/admin');
        });
    })->create();
