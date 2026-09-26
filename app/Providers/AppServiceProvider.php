<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\ThemeTemplate;
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
use App\Domain\Blocks\Definitions\SocialLinksBlockDefinition;
use App\Domain\Blocks\Definitions\SiteIdentityBlockDefinition;
use App\Domain\Blocks\Definitions\CopyrightBlockDefinition;
use App\Domain\Blocks\Definitions\BackToTopBlockDefinition;
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
use App\Domain\Blocks\Definitions\CatalogBlockDefinition;
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
use App\Domain\Blocks\Definitions\LangSwitcherBlockDefinition;
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
use App\Domain\Blocks\Definitions\LinearGalleryBlockDefinition;
use App\Domain\Blocks\Definitions\PostTitleBlockDefinition;
use App\Domain\Blocks\Definitions\PostContentBlockDefinition;
use App\Domain\Blocks\Definitions\PostImageBlockDefinition;
use App\Domain\Blocks\Definitions\PostVideoBlockDefinition;
use App\Domain\Blocks\Definitions\PostMetaBlockDefinition;
use App\Domain\Blocks\Definitions\PostExcerptBlockDefinition;
use App\Domain\Blocks\Definitions\PostNavigationBlockDefinition;
use App\Domain\Blocks\Definitions\PostLoopBlockDefinition;
use App\Domain\Blocks\Definitions\CategoryHeaderBlockDefinition;
use App\Domain\Blocks\Definitions\ArchivePaginationBlockDefinition;
use App\Domain\Blocks\Definitions\SliderBlockDefinition;
use App\Domain\Blocks\Definitions\SlideBlockDefinition;
use App\Domain\Blocks\Definitions\SliderRefBlockDefinition;
use App\Domain\Blocks\Definitions\ShapeBlockDefinition;
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

        // Module Framework — per-request cached enablement resolver.
        $this->app->singleton(\App\Domain\Modules\Services\ModuleRegistry::class);

        // Inline-edit layer: ambient render mode (default Publish) + the
        // sp_editable() Blade helper. Both are inert on the publish path.
        $this->app->singleton(\App\Domain\Publishing\Rendering\RenderContext::class);
        require_once app_path('Support/Rendering/sp_helpers.php');

        $this->app->singleton(BlockRegistry::class, function () {
            $registry = new BlockRegistry();
            $registry->register(new HeroBlockDefinition());
            $registry->register(new TextBlockDefinition());
            $registry->register(new ImageBlockDefinition());
            $registry->register(new ColumnBlockDefinition());
            $registry->register(new ColumnsBlockDefinition());
            $registry->register(new HeadingBlockDefinition());
            $registry->register(new DividerBlockDefinition());
            // Site chrome blocks (grid areas as blocks, stage 2)
            $registry->register(new SocialLinksBlockDefinition());
            $registry->register(new SiteIdentityBlockDefinition());
            $registry->register(new CopyrightBlockDefinition());
            $registry->register(new BackToTopBlockDefinition());
            $registry->register(new PullquoteBlockDefinition());
            $registry->register(new ButtonBlockDefinition());
            $registry->register(new RowBlockDefinition());
            $registry->register(new SectionBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\BulletinSectionBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\EventCardBlockDefinition());
            $registry->register(new SpacerBlockDefinition());
            $registry->register(new VideoBlockDefinition());
            $registry->register(new HtmlEmbedBlockDefinition());
            $registry->register(new TabsBlockDefinition());
            $registry->register(new AccordionBlockDefinition());
            $registry->register(new CatalogBlockDefinition());
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
            $registry->register(new LangSwitcherBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\CollectionCategoriesBlockDefinition());
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
            $registry->register(new LinearGalleryBlockDefinition());

            // Dynamic content blocks (for theme builder templates)
            $registry->register(new PostTitleBlockDefinition());
            $registry->register(new PostContentBlockDefinition());
            $registry->register(new PostImageBlockDefinition());
            $registry->register(new PostVideoBlockDefinition());
            $registry->register(new PostMetaBlockDefinition());
            $registry->register(new PostExcerptBlockDefinition());
            $registry->register(new PostNavigationBlockDefinition());
            $registry->register(new PostLoopBlockDefinition());
            $registry->register(new CategoryHeaderBlockDefinition());
            $registry->register(new ArchivePaginationBlockDefinition());

            // Collections (Track G2): record slot blocks + search islands
            $registry->register(new \App\Domain\Blocks\Definitions\RecordTitleBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\RecordImageBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\FieldValueBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\RecordLoopBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\SearchBoxBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\FacetFilterBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\ResultsGridBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\QueryStatBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\QueryTableBlockDefinition());

            // Slider system (library entity root + slide + page-side ref + shape primitive)
            $registry->register(new SliderBlockDefinition());
            $registry->register(new SlideBlockDefinition());
            $registry->register(new SliderRefBlockDefinition());
            $registry->register(new ShapeBlockDefinition());

            // Global Sections (P2): page-side embed of a reusable section entity
            $registry->register(new \App\Domain\Blocks\Definitions\GlobalRefBlockDefinition());

            // Interactive app-blocks (self-hosted runtime via AppToolRender)
            $registry->register(new \App\Domain\Blocks\Definitions\BreathingPacerBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\MeditationTimerBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\PelvicTrainerBlockDefinition());
            $registry->register(new \App\Domain\Blocks\Definitions\PartnerDeckBlockDefinition());

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

        // Module receiving endpoints (token-authenticated). Keyed by the module
        // token when present, else the caller IP.
        RateLimiter::for('module-api', function (Request $request) {
            $token = $request->attributes->get('module_token');
            $key = $token?->getKey() ?: $request->ip();
            return Limit::perMinute(60)->by('module-api:' . $key);
        });

        // Redis fallback (F26): only replace drivers that are actually set to
        // redis. The old unconditional override turned the test suite's
        // array/array/sync into file/database/database — a FILE cache that
        // outlives test runs (stale entries) and a real DB session table.
        if (!config('cms.redis_enabled')) {
            if (config('cache.default') === 'redis') {
                config(['cache.default' => 'file']);
            }
            if (config('session.driver') === 'redis') {
                config(['session.driver' => 'database']);
            }
            if (config('queue.default') === 'redis') {
                config(['queue.default' => 'database']);
            }
            if (config('broadcasting.default') === 'reverb') {
                config(['broadcasting.default' => null]);
            }
        }

        Relation::enforceMorphMap([
            'page' => Page::class,
            'post' => Post::class,
            'template' => ThemeTemplate::class,
            'slider' => \App\Models\Slider::class,
            'global_section' => \App\Models\GlobalSection::class,
            'collection' => \App\Models\ContentCollection::class,
            'record' => \App\Models\Record::class,
            'query' => \App\Models\SavedQuery::class,
        ]);

        // Related posts on single posts — opt-in via site settings.related_posts.enabled.
        // Injected through the page_render filter (runs before the serve→static asset
        // rewrite, so featured images resolve correctly for both preview and static).
        app(HookDispatcher::class)->addFilter('page_render', function (string $html, $content, $site): string {
            if (!($content instanceof Post)) return $html;
            if (empty($site->settings['related_posts']['enabled'])) return $html;
            $limit = max(2, min(8, (int) ($site->settings['related_posts']['limit'] ?? 4)));
            $related = Post::where('site_id', $site->id)
                ->where('status', 'published')
                ->where('id', '!=', $content->id)
                ->when($content->category_id, fn ($q) => $q->where('category_id', $content->category_id))
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
            if ($related->count() < 2) return $html;
            $section = view('publishing._related-posts', ['posts' => $related, 'site' => $site])->render();
            $pos = strripos($html, '</main>');
            return $pos !== false ? substr($html, 0, $pos) . $section . substr($html, $pos) : $html;
        });

        // Right-column sidebar on single posts (banner + ПОСЛЕДНО + category lists) —
        // opt-in via settings.post_sidebar.enabled. Injected before </main>; a client
        // script re-arranges it into a two-column layout below the hero.
        app(HookDispatcher::class)->addFilter('page_render', function (string $html, $content, $site): string {
            if (!($content instanceof Post)) return $html;
            $cfg = $site->settings['post_sidebar'] ?? null;
            if (empty($cfg['enabled'])) return $html;

            $latest = Post::where('site_id', $site->id)
                ->where('status', 'published')
                ->where('id', '!=', $content->id)
                ->orderByDesc('published_at')
                ->limit((int) ($cfg['latest_limit'] ?? 6))
                ->get();

            $catGroups = [];
            $slugs = $cfg['category_slugs'] ?? null;
            $cats = $slugs
                ? Category::where('site_id', $site->id)->whereIn('slug', $slugs)->get()
                    ->sortBy(fn ($c) => array_search($c->slug, $slugs))->values()
                : Category::where('site_id', $site->id)->withCount('posts')
                    ->orderByDesc('posts_count')->limit((int) ($cfg['category_groups'] ?? 4))->get();
            foreach ($cats as $c) {
                $posts = Post::where('site_id', $site->id)
                    ->where('status', 'published')
                    ->where('category_id', $c->id)
                    ->orderByDesc('published_at')
                    ->limit((int) ($cfg['per_category'] ?? 4))
                    ->get();
                if ($posts->count()) {
                    $catGroups[] = [
                        'name' => $c->name,
                        'url' => '/' . \App\Domain\Publishing\Services\LocalePaths::categoryBase($site) . $c->slug . '/',
                        'posts' => $posts,
                    ];
                }
            }

            $bannerUrl = $cfg['banner_url'] ?? 'https://picsum.photos/seed/creativeeurope/300/240';
            $bannerLink = $cfg['banner_link'] ?? 'https://creativeeurope.bg';

            $aside = view('publishing._post-sidebar', compact('latest', 'catGroups', 'bannerUrl', 'bannerLink', 'site'))->render();
            $pos = strripos($html, '</main>');
            if ($pos !== false) $html = substr($html, 0, $pos) . $aside . substr($html, $pos);

            // Match the front-page footer: inject the homepage's footer html-embed
            // blocks (settings.post_sidebar.footer_block_ids) at page bottom; the
            // default rich footer is hidden on posts via CSS (body.has-hero .site-footer).
            $footerIds = $cfg['footer_block_ids'] ?? [];
            if (!empty($footerIds)) {
                $footHtml = '';
                foreach ($footerIds as $bid) {
                    $blk = Block::find($bid);
                    if ($blk) {
                        $d = is_array($blk->data) ? $blk->data : (array) $blk->data;
                        $footHtml .= $d['html'] ?? $d['content'] ?? '';
                    }
                }
                if ($footHtml !== '') {
                    $footHtml = '<div class="post-home-footer">' . $footHtml . '</div>';
                    $bpos = strripos($html, '</body>');
                    $html = $bpos !== false ? substr($html, 0, $bpos) . $footHtml . substr($html, $bpos) : $html . $footHtml;
                }
            }

            return $html;
        });

        // Explicit route model bindings for non-standard model locations
        Route::model('themeTemplate', ThemeTemplate::class);
        Route::model('issue', \App\Domain\IssueComposer\Models\MagazineIssue::class);
        Route::model('collection', \App\Models\ContentCollection::class);

        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Block::class, BlockPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(ThemeTemplate::class, \App\Policies\ThemeTemplatePolicy::class);

        // Module Framework abilities → role-hierarchy thresholds (docs: RBAC).
        \App\Domain\Modules\Support\ModulePermissions::registerGates();
    }
}
