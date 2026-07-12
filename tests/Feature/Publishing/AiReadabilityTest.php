<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\LlmsTxtGenerator;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Domain\Publishing\Services\RssFeedGenerator;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Domain\Publishing\Services\StructuredDataService;
use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Track F4 — AI readability: llms.txt, AI-crawler robots toggles,
 * per-category feeds + full-content option, accurate dateModified.
 */
class AiReadabilityTest extends TestCase
{
    private function makeSite(array $settings = [], array $seoDefaults = []): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => $settings,
            'seo_defaults' => $seoDefaults,
        ]);
    }

    // ─── llms.txt ───

    public function test_llms_txt_follows_the_spec_structure(): void
    {
        $site = $this->makeSite(['business_description' => 'We fix roofs fast.']);
        Page::factory()->create(['site_id' => $site->id, 'title' => 'About Us', 'slug' => 'about', 'status' => 'published']);
        Post::factory()->create(['site_id' => $site->id, 'category_id' => null, 'title' => 'Roof Care 101', 'excerpt' => 'The basics.', 'status' => 'published']);

        $md = app(LlmsTxtGenerator::class)->generate($site->fresh());

        $this->assertStringStartsWith("# {$site->name}\n", $md);
        $this->assertStringContainsString('> We fix roofs fast.', $md);
        $this->assertStringContainsString('## Pages', $md);
        $this->assertStringContainsString('[About Us](', $md);
        $this->assertStringContainsString('## Posts', $md);
        $this->assertStringContainsString('[Roof Care 101](', $md);
        $this->assertStringContainsString(': The basics.', $md);
        $this->assertStringContainsString('## Optional', $md);
        $this->assertStringContainsString('/sitemap.xml', $md);
    }

    public function test_llms_txt_can_be_disabled_per_site(): void
    {
        $site = $this->makeSite(['llms_txt' => false]);
        $this->assertNull(app(LlmsTxtGenerator::class)->generate($site));
    }

    // ─── AI-crawler robots toggles ───

    public function test_ai_crawlers_are_allowed_by_default(): void
    {
        $robots = app(RobotsGenerator::class)->generate($this->makeSite());
        $this->assertStringContainsString("User-agent: *\nAllow: /", $robots);
        $this->assertStringNotContainsString('GPTBot', $robots);
        $this->assertStringNotContainsString('ClaudeBot', $robots);
    }

    public function test_disallowed_ai_crawlers_get_robots_blocks(): void
    {
        $site = $this->makeSite(['ai_crawlers_disallowed' => ['GPTBot', 'Bytespider', 'NotARealBot']]);
        $robots = app(RobotsGenerator::class)->generate($site);

        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: Bytespider\nDisallow: /", $robots);
        // unknown names are ignored, ClaudeBot stays allowed
        $this->assertStringNotContainsString('NotARealBot', $robots);
        $this->assertStringNotContainsString('ClaudeBot', $robots);
        // generic crawl + sitemap intact
        $this->assertStringContainsString("User-agent: *\nAllow: /", $robots);
        $this->assertStringContainsString('Sitemap:', $robots);
    }

    public function test_custom_robots_override_stays_verbatim(): void
    {
        $site = $this->makeSite(['robots' => "User-agent: *\nDisallow: /", 'ai_crawlers_disallowed' => ['GPTBot']]);
        $this->assertSame("User-agent: *\nDisallow: /", app(RobotsGenerator::class)->generate($site));
    }

    // ─── Feeds ───

    private function postWithBlocks(Site $site, ?Category $category = null): Post
    {
        $post = Post::factory()->create([
            'site_id' => $site->id,
            'category_id' => $category?->id,
            'title' => 'Feed Post',
            'slug' => 'feed-post',
            'excerpt' => 'Short version.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Block::factory()->create([
            'blockable_id' => $post->id, 'blockable_type' => 'post',
            'type' => 'text', 'order' => 0,
            'data' => ['content' => '<p>The full body of the post.</p>'],
        ]);

        return $post->fresh();
    }

    public function test_feed_defaults_to_excerpt_only(): void
    {
        $site = $this->makeSite();
        $this->postWithBlocks($site);

        $xml = app(RssFeedGenerator::class)->generate($site);
        $this->assertStringContainsString('<description>Short version.</description>', $xml);
        $this->assertStringNotContainsString('content:encoded', $xml);
    }

    public function test_feed_full_content_setting_emits_content_encoded(): void
    {
        $site = $this->makeSite([], ['feed_full_content' => true]);
        $this->postWithBlocks($site);

        $xml = app(RssFeedGenerator::class)->generate($site);
        $this->assertStringContainsString('xmlns:content=', $xml);
        $this->assertStringContainsString('<content:encoded><![CDATA[<p>The full body of the post.</p>]]></content:encoded>', $xml);
    }

    public function test_category_feed_has_self_link_and_canonical_post_urls(): void
    {
        $site = $this->makeSite();
        $category = Category::factory()->create(['site_id' => $site->id, 'name' => 'Guides', 'slug' => 'guides']);
        $this->postWithBlocks($site, $category);

        $xml = app(RssFeedGenerator::class)->generateForCategory($site, $category);
        $this->assertStringContainsString('/guides/feed.xml" rel="self"', $xml);
        // canonical /{category}/{slug} URL, not the legacy /blog/ scheme
        $this->assertStringContainsString('/guides/feed-post/</link>', $xml);
        $this->assertStringNotContainsString('/blog/', $xml);
    }

    // ─── Accurate dateModified ───

    public function test_sitemap_and_schema_prefer_content_modified_at(): void
    {
        $site = $this->makeSite();
        $post = Post::factory()->create([
            'site_id' => $site->id, 'category_id' => null, 'status' => 'published',
            'content_modified_at' => '2026-01-15 10:00:00',
        ]);
        // simulate staleness bookkeeping bumping updated_at
        Post::whereKey($post->id)->update(['needs_republish' => true, 'needs_republish_reason' => 'test']);
        $post = $post->fresh();
        $this->assertNotEquals('2026-01-15', $post->updated_at->format('Y-m-d'));

        $sitemap = app(SitemapGenerator::class)->generate($site);
        $this->assertStringContainsString('2026-01-15T10:00:00', $sitemap);

        $json = app(StructuredDataService::class)->generateForPost($post, $site);
        $this->assertStringContainsString('"dateModified":"2026-01-15T10:00:00', $json);
    }

    public function test_staleness_flag_does_not_touch_content_modified_at(): void
    {
        $site = $this->makeSite();
        $post = Post::factory()->create([
            'site_id' => $site->id, 'category_id' => null, 'status' => 'published',
            'content_modified_at' => '2026-01-15 10:00:00',
        ]);

        Post::whereKey($post->id)->update(['needs_republish' => true, 'needs_republish_reason' => 'test']);

        $this->assertSame('2026-01-15 10:00:00', $post->fresh()->content_modified_at->format('Y-m-d H:i:s'));
    }

    public function test_content_edits_stamp_content_modified_at(): void
    {
        $site = $this->makeSite();
        $post = Post::factory()->create(['site_id' => $site->id, 'category_id' => null]);
        $this->assertNull($post->content_modified_at);

        app(\App\Domain\Posts\Services\PostService::class)->updatePost($post, ['title' => 'New Title']);
        $this->assertNotNull($post->fresh()->content_modified_at);

        // status-only change does not stamp
        $page = Page::factory()->create(['site_id' => $site->id]);
        app(\App\Domain\Pages\Services\PageService::class)->updatePage($page, ['status' => 'published']);
        $this->assertNull($page->fresh()->content_modified_at);
    }
}
