<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        if (!config('cms.redis_enabled')) {
            return;
        }

        parent::register();
    }

    public function boot(): void
    {
        if (!config('cms.redis_enabled')) {
            return;
        }

        parent::boot();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
