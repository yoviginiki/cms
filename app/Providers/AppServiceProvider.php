<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!config('cms.redis_enabled')) {
            config([
                'cache.default' => 'file',
                'session.driver' => 'database',
                'queue.default' => 'database',
                'broadcasting.default' => null,
            ]);
        }
    }
}
