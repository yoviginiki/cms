<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\SeoService;
use App\Domain\Publishing\Services\StructuredDataService;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * LocalBusiness structured data (Track F / F1) — the Full-Site generator records
 * a business type on the site; the publish head then emits a LocalBusiness JSON-LD
 * node (with a specific schema.org subtype) on the homepage for local SEO.
 */
class StructuredDataTest extends TestCase
{
    private function siteWith(?string $businessType): Site
    {
        $this->setTenantScope($this->owner);
        return Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => $businessType ? ['business_type' => $businessType, 'business_description' => 'We keep you comfortable.'] : [],
        ]);
    }

    public function test_local_business_uses_a_specific_schema_subtype(): void
    {
        $svc = app(StructuredDataService::class);

        $hvac = $svc->generateLocalBusiness($this->siteWith('HVAC company'));
        $this->assertStringContainsString('"@type":"HVACBusiness"', $hvac);
        $this->assertStringContainsString('"description":"We keep you comfortable."', $hvac);

        $this->assertStringContainsString('"@type":"Plumber"', $svc->generateLocalBusiness($this->siteWith('plumbing service')));
        $this->assertStringContainsString('"@type":"LodgingBusiness"', $svc->generateLocalBusiness($this->siteWith('boutique hotel')));
        // unknown industry → generic LocalBusiness
        $this->assertStringContainsString('"@type":"LocalBusiness"', $svc->generateLocalBusiness($this->siteWith('artisanal widget forge')));
    }

    public function test_post_schema_is_blogposting_with_author(): void
    {
        $site = $this->siteWith(null);
        $post = Post::factory()->create([
            'site_id' => $site->id, 'author_id' => $this->owner->id,
            'title' => 'When to Replace Your Roof', 'excerpt' => 'Signs it is time.', 'status' => 'published',
        ]);

        $json = app(StructuredDataService::class)->generateForPost($post->fresh(), $site);
        $this->assertStringContainsString('"@type":"BlogPosting"', $json);
        $this->assertStringContainsString('"@type":"Person"', $json);
        $this->assertStringContainsString('"mainEntityOfPage"', $json);
    }

    public function test_no_local_business_without_a_business_type(): void
    {
        $this->assertNull(app(StructuredDataService::class)->generateLocalBusiness($this->siteWith(null)));
    }

    public function test_head_emits_local_business_on_homepage_only(): void
    {
        $site = $this->siteWith('day spa');
        $home = Page::factory()->create(['site_id' => $site->id, 'slug' => 'home', 'title' => 'Home', 'status' => 'published']);
        $about = Page::factory()->create(['site_id' => $site->id, 'slug' => 'about', 'title' => 'About', 'status' => 'published']);

        $seo = app(SeoService::class);
        $this->assertStringContainsString('"@type":"HealthAndBeautyBusiness"', $seo->generatePageHead($home, $site));
        $this->assertStringNotContainsString('HealthAndBeautyBusiness', $seo->generatePageHead($about, $site));
    }
}
