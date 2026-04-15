<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Policies\AssetPolicy;
use App\Policies\BlockPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\PagePolicy;
use App\Policies\PostPolicy;
use App\Policies\SitePolicy;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Block::class, BlockPolicy::class);
    }
}
