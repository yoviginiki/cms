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
use App\Policies\TagPolicy;
use App\Models\Tag;
use App\Domain\Blocks\Definitions\ColumnBlockDefinition;
use App\Domain\Blocks\Definitions\ColumnsBlockDefinition;
use App\Domain\Blocks\Definitions\DividerBlockDefinition;
use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\HeroBlockDefinition;
use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Blocks\Definitions\PullquoteBlockDefinition;
use App\Domain\Blocks\Definitions\TextBlockDefinition;
use App\Domain\Blocks\Definitions\ButtonBlockDefinition;
use App\Domain\Blocks\Definitions\RowBlockDefinition;
use App\Domain\Blocks\Definitions\SectionBlockDefinition;
use App\Domain\Blocks\Definitions\SpacerBlockDefinition;
use App\Domain\Blocks\Definitions\VideoBlockDefinition;
use App\Domain\Blocks\Definitions\HtmlEmbedBlockDefinition;
use App\Domain\Blocks\Definitions\TabsBlockDefinition;
use App\Domain\Blocks\Definitions\AccordionBlockDefinition;
use App\Domain\Blocks\Definitions\CodeBlockDefinition;
use App\Domain\Blocks\Definitions\ContactFormBlockDefinition;
use App\Domain\Blocks\Definitions\RichTextBlockDefinition;
use App\Domain\Blocks\Definitions\FlipbookBlockDefinition;
use App\Domain\Blocks\Definitions\ScrollPageBlockDefinition;
use App\Domain\Blocks\Definitions\ParagraphBlockDefinition;
use App\Domain\Blocks\Definitions\ListBlockDefinition;
use App\Domain\Blocks\Definitions\CaptionBlockDefinition;
use App\Domain\Blocks\Definitions\DropcapBlockDefinition;
use App\Domain\Blocks\Definitions\FootnoteBlockDefinition;
use App\Domain\Blocks\Definitions\ContainerBlockDefinition;
use App\Domain\Blocks\Definitions\GridBlockDefinition;
use App\Domain\Blocks\Definitions\GroupBlockDefinition;
use App\Domain\Blocks\Definitions\FullbleedBlockDefinition;
use App\Domain\Blocks\Definitions\GalleryBlockDefinition;
use App\Domain\Blocks\Definitions\AudioBlockDefinition;
use App\Domain\Blocks\Definitions\ImagecaptionBlockDefinition;
use App\Domain\Blocks\Definitions\IconBlockDefinition;
use App\Domain\Blocks\Definitions\CtabannerBlockDefinition;
use App\Domain\Blocks\Definitions\TestimonialBlockDefinition;
use App\Domain\Blocks\Definitions\LogostripBlockDefinition;
use App\Domain\Blocks\Definitions\StatsBlockDefinition;
use App\Domain\Blocks\Definitions\SidenoteBlockDefinition;
use App\Domain\Blocks\Definitions\RunningtextBlockDefinition;
use App\Domain\Blocks\Definitions\TextdividerBlockDefinition;
use App\Domain\Blocks\Definitions\OverlapBlockDefinition;
use App\Domain\Blocks\Definitions\AnchormenuBlockDefinition;
use App\Domain\Blocks\Definitions\BreadcrumbsBlockDefinition;
use App\Domain\Blocks\Definitions\TocBlockDefinition;
use App\Domain\Blocks\Definitions\MenuBlockDefinition;
use App\Domain\Blocks\Definitions\ReadingprogressBlockDefinition;
use App\Domain\Blocks\Definitions\FeaturegridBlockDefinition;
use App\Domain\Blocks\Definitions\FeaturecomparisonBlockDefinition;
use App\Domain\Blocks\Definitions\PricingcardBlockDefinition;
use App\Domain\Blocks\Definitions\PricingtableBlockDefinition;
use App\Domain\Blocks\Definitions\TableBlockDefinition;
use App\Domain\Blocks\Definitions\TimelineBlockDefinition;
use App\Domain\Blocks\Definitions\TooltipBlockDefinition;
use App\Domain\Blocks\Definitions\PostcardBlockDefinition;
use App\Domain\Blocks\Definitions\PostgridBlockDefinition;
use App\Domain\Blocks\Definitions\AuthorboxBlockDefinition;
use App\Domain\Blocks\Definitions\ModalBlockDefinition;
use App\Domain\Blocks\Definitions\StickysidebarBlockDefinition;
use App\Domain\Blocks\Definitions\LatestpostsBlockDefinition;
use App\Domain\Blocks\Definitions\RelatedpostsBlockDefinition;
use App\Domain\Blocks\Definitions\CategorylistBlockDefinition;
use App\Domain\Blocks\Definitions\SocialembedBlockDefinition;
use App\Domain\Blocks\Definitions\MapBlockDefinition;
use App\Domain\Blocks\Definitions\ChartBlockDefinition;
use App\Domain\Blocks\Definitions\NewsletterBlockDefinition;
use App\Domain\Blocks\Definitions\CustomformBlockDefinition;
use App\Domain\Blocks\Definitions\PaywallBlockDefinition;
use App\Domain\Blocks\Definitions\SharebuttonsBlockDefinition;
use App\Domain\Blocks\Definitions\BeforeafterBlockDefinition;
use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Hooks\HookDispatcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HookDispatcher::class);

        $this->app->singleton(BlockRegistry::class, function () {
            $registry = new BlockRegistry();
            $registry->register(new HeroBlockDefinition());
            $registry->register(new TextBlockDefinition());
            $registry->register(new ImageBlockDefinition());
            $registry->register(new ColumnBlockDefinition());
            $registry->register(new ColumnsBlockDefinition());
            $registry->register(new HeadingBlockDefinition());
            $registry->register(new DividerBlockDefinition());
            $registry->register(new PullquoteBlockDefinition());
            $registry->register(new ButtonBlockDefinition());
            $registry->register(new RowBlockDefinition());
            $registry->register(new SectionBlockDefinition());
            $registry->register(new SpacerBlockDefinition());
            $registry->register(new VideoBlockDefinition());
            $registry->register(new HtmlEmbedBlockDefinition());
            $registry->register(new TabsBlockDefinition());
            $registry->register(new AccordionBlockDefinition());
            $registry->register(new CodeBlockDefinition());
            $registry->register(new ContactFormBlockDefinition());
            $registry->register(new RichTextBlockDefinition());
            $registry->register(new FlipbookBlockDefinition());
            $registry->register(new ScrollPageBlockDefinition());
            $registry->register(new ParagraphBlockDefinition());
            $registry->register(new ListBlockDefinition());
            $registry->register(new CaptionBlockDefinition());
            $registry->register(new DropcapBlockDefinition());
            $registry->register(new FootnoteBlockDefinition());
            $registry->register(new ContainerBlockDefinition());
            $registry->register(new GridBlockDefinition());
            $registry->register(new GroupBlockDefinition());
            $registry->register(new FullbleedBlockDefinition());
            $registry->register(new GalleryBlockDefinition());
            $registry->register(new AudioBlockDefinition());
            $registry->register(new ImagecaptionBlockDefinition());
            $registry->register(new IconBlockDefinition());
            $registry->register(new CtabannerBlockDefinition());
            $registry->register(new TestimonialBlockDefinition());
            $registry->register(new LogostripBlockDefinition());
            $registry->register(new StatsBlockDefinition());
            $registry->register(new SidenoteBlockDefinition());
            $registry->register(new RunningtextBlockDefinition());
            $registry->register(new TextdividerBlockDefinition());
            $registry->register(new OverlapBlockDefinition());
            $registry->register(new AnchormenuBlockDefinition());
            $registry->register(new BreadcrumbsBlockDefinition());
            $registry->register(new TocBlockDefinition());
            $registry->register(new MenuBlockDefinition());
            $registry->register(new ReadingprogressBlockDefinition());
            $registry->register(new FeaturegridBlockDefinition());
            $registry->register(new FeaturecomparisonBlockDefinition());
            $registry->register(new PricingcardBlockDefinition());
            $registry->register(new PricingtableBlockDefinition());
            $registry->register(new TableBlockDefinition());
            $registry->register(new TimelineBlockDefinition());
            $registry->register(new TooltipBlockDefinition());
            $registry->register(new PostcardBlockDefinition());
            $registry->register(new PostgridBlockDefinition());
            $registry->register(new AuthorboxBlockDefinition());
            $registry->register(new ModalBlockDefinition());
            $registry->register(new StickysidebarBlockDefinition());
            $registry->register(new LatestpostsBlockDefinition());
            $registry->register(new RelatedpostsBlockDefinition());
            $registry->register(new CategorylistBlockDefinition());
            $registry->register(new SocialembedBlockDefinition());
            $registry->register(new MapBlockDefinition());
            $registry->register(new ChartBlockDefinition());
            $registry->register(new NewsletterBlockDefinition());
            $registry->register(new CustomformBlockDefinition());
            $registry->register(new PaywallBlockDefinition());
            $registry->register(new SharebuttonsBlockDefinition());
            $registry->register(new BeforeafterBlockDefinition());

            return $registry;
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('publish', function (Request $request) {
            return Limit::perMinute(5);
        });

        RateLimiter::for('block-sync', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

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

        // Explicit route model bindings for non-standard model locations
        Route::model('issue', \App\Domain\IssueComposer\Models\MagazineIssue::class);
        Route::model('item', \App\Domain\IssueComposer\Models\IssueContentItem::class);
        Route::model('session', \App\Models\Magazine\WizardSession::class);

        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Block::class, BlockPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(\App\Models\Magazine\WizardSession::class, \App\Policies\Magazine\WizardSessionPolicy::class);
    }
}
