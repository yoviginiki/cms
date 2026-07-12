<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\SeoService;
use App\Domain\Publishing\Services\StructuredDataService;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Track F2 — per-page SEO controls: canonical override, decoupled robots
 * toggles, description fallback chain, verification tags, publisher identity.
 */
class SeoHeadTest extends TestCase
{
    private function site(array $seoDefaults = [], array $settings = []): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seo_defaults' => $seoDefaults,
            'settings' => $settings,
        ]);
    }

    private function renderHead(Page|Post $content, Site $site): string
    {
        return app(SeoService::class)->generatePageHead($content, $site);
    }

    public function test_canonical_override_replaces_the_computed_url(): void
    {
        $site = $this->site();
        $page = Page::factory()->create([
            'site_id' => $site->id, 'slug' => 'about', 'title' => 'About', 'status' => 'published',
            'seo_meta' => ['canonical' => 'https://example.com/legacy-about/'],
        ]);

        $head = $this->renderHead($page, $site);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/legacy-about/">', $head);
        $this->assertStringContainsString('og:url" content="https://example.com/legacy-about/"', $head);
    }

    public function test_invalid_canonical_override_is_ignored(): void
    {
        $site = $this->site();
        $page = Page::factory()->create([
            'site_id' => $site->id, 'slug' => 'about', 'title' => 'About', 'status' => 'published',
            'seo_meta' => ['canonical' => 'not-a-url'],
        ]);

        $head = $this->renderHead($page, $site);
        $this->assertStringContainsString('/about">', $head);
        $this->assertStringNotContainsString('not-a-url', $head);
    }

    public function test_robots_toggles_are_independent(): void
    {
        $site = $this->site();
        $noIndex = Page::factory()->create(['site_id' => $site->id, 'slug' => 'a', 'title' => 'A', 'seo_meta' => ['no_index' => true]]);
        $noFollow = Page::factory()->create(['site_id' => $site->id, 'slug' => 'b', 'title' => 'B', 'seo_meta' => ['no_follow' => true]]);
        $both = Page::factory()->create(['site_id' => $site->id, 'slug' => 'c', 'title' => 'C', 'seo_meta' => ['no_index' => true, 'no_follow' => true]]);
        $default = Page::factory()->create(['site_id' => $site->id, 'slug' => 'd', 'title' => 'D']);

        $this->assertStringContainsString('<meta name="robots" content="noindex">', $this->renderHead($noIndex, $site));
        $this->assertStringContainsString('<meta name="robots" content="nofollow">', $this->renderHead($noFollow, $site));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $this->renderHead($both, $site));
        $this->assertStringNotContainsString('name="robots"', $this->renderHead($default, $site));
    }

    public function test_description_falls_back_to_site_default_when_page_has_no_content(): void
    {
        $site = $this->site(['description' => 'Site-wide default description.']);
        $page = Page::factory()->create(['site_id' => $site->id, 'slug' => 'empty', 'title' => 'Empty']);

        $this->assertStringContainsString(
            'name="description" content="Site-wide default description."',
            $this->renderHead($page, $site)
        );
    }

    public function test_post_description_falls_back_to_excerpt(): void
    {
        $site = $this->site(['description' => 'Site default.']);
        $post = Post::factory()->create([
            'site_id' => $site->id, 'category_id' => null, 'title' => 'P', 'excerpt' => 'The post excerpt wins.',
        ]);

        $this->assertStringContainsString(
            'name="description" content="The post excerpt wins."',
            $this->renderHead($post->fresh(), $site)
        );
    }

    public function test_verification_tags_are_emitted_from_site_settings(): void
    {
        $site = $this->site(['verification_google' => 'g-token-123', 'verification_bing' => 'b-token-456']);
        $page = Page::factory()->create(['site_id' => $site->id, 'slug' => 'home', 'title' => 'Home']);

        $head = $this->renderHead($page, $site);
        $this->assertStringContainsString('<meta name="google-site-verification" content="g-token-123">', $head);
        $this->assertStringContainsString('<meta name="msvalidate.01" content="b-token-456">', $head);
    }

    public function test_publisher_identity_includes_logo_and_social_profiles(): void
    {
        $site = $this->site([], [
            'logo_url' => 'https://cdn.example.com/logo.png',
            'social_links' => ['facebook' => 'https://facebook.com/acme', 'x' => 'https://x.com/acme', 'empty' => ''],
        ]);
        $post = Post::factory()->create(['site_id' => $site->id, 'category_id' => null, 'title' => 'P2', 'author_id' => $this->owner->id]);

        $json = app(StructuredDataService::class)->generateForPost($post->fresh(), $site);
        $this->assertStringContainsString('"logo":{"@type":"ImageObject","url":"https://cdn.example.com/logo.png"}', $json);
        $this->assertStringContainsString('"sameAs":["https://facebook.com/acme","https://x.com/acme"]', $json);
    }
}
