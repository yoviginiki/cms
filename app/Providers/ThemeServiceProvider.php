<?php

namespace App\Providers;

use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeOverride;
use App\Services\Theme\CurrentTheme;
use App\Services\Theme\ThemeResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeResolver::class);
        $this->app->scoped(CurrentTheme::class);
    }

    public function boot(): void
    {
        Blade::directive('themeVariables', function () {
            return "<?php echo app(\\App\\Services\\Theme\\CurrentTheme::class)->resolved()->toCssVariables(); ?>";
        });

        // Cache invalidation observers
        $flush = function ($model) {
            try {
                $tenantId = $model->tenant_id ?? $model->site?->tenant_id ?? null;
                if ($tenantId) {
                    app(ThemeResolver::class)->invalidateForTenant($tenantId);
                }
            } catch (\Throwable) {}
        };

        Theme::saved($flush);
        Theme::deleted($flush);
        ThemeAssignment::saved($flush);
        ThemeAssignment::deleted($flush);
        ThemeOverride::saved($flush);
        ThemeOverride::deleted($flush);
    }
}
