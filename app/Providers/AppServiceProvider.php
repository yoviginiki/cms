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
use App\Domain\Blocks\Definitions\ColumnsBlockDefinition;
use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\HeroBlockDefinition;
use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Blocks\Definitions\TextBlockDefinition;
use App\Domain\Blocks\Services\BlockRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class, function () {
            $registry = new BlockRegistry();
            $registry->register(new HeroBlockDefinition());
            $registry->register(new TextBlockDefinition());
            $registry->register(new ImageBlockDefinition());
            $registry->register(new ColumnsBlockDefinition());
            $registry->register(new HeadingBlockDefinition());

            return $registry;
        });
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

        Relation::enforceMorphMap([
            'page' => Page::class,
            'post' => Post::class,
        ]);

        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Block::class, BlockPolicy::class);
    }
}
