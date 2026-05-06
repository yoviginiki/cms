<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class DynamicSiteTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'mysite',
        ]);
    }

    public function test_homepage_returns_200(): void
    {
        Page::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'home',
        ]);
        $this->site->update(['settings' => ['homepage_type' => 'page']]);

        $this->actingAsOwner()
            ->get('/sites/mysite')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_page_by_slug_returns_200(): void
    {
        Page::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'about',
        ]);

        $this->actingAsOwner()
            ->get('/sites/mysite/about')
            ->assertStatus(200);
    }

    public function test_post_by_category_and_slug_returns_200(): void
    {
        $category = Category::create([
            'site_id' => $this->site->id,
            'name' => 'News',
            'slug' => 'news',
            'sort_order' => 0,
        ]);

        Post::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'hello-world',
            'category_id' => $category->id,
        ]);

        $this->actingAsOwner()
            ->get('/sites/mysite/news/hello-world')
            ->assertStatus(200);
    }

    public function test_post_url_uses_category_slug(): void
    {
        $category = Category::create([
            'site_id' => $this->site->id,
            'name' => 'Tech',
            'slug' => 'tech',
            'sort_order' => 0,
        ]);

        Post::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'my-post',
            'category_id' => $category->id,
        ]);

        // /{category}/{slug} URL must work
        $this->actingAsOwner()
            ->get('/sites/mysite/tech/my-post')
            ->assertStatus(200);
    }

    public function test_nonexistent_page_returns_404(): void
    {
        $this->actingAsOwner()
            ->get('/sites/mysite/nonexistent')
            ->assertStatus(404);
    }

    public function test_wrong_site_slug_returns_404(): void
    {
        $this->actingAsOwner()
            ->get('/sites/wrongslug')
            ->assertStatus(404);
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get('/sites/mysite')
            ->assertRedirect('/admin');
    }

    public function test_homepage_with_configured_page(): void
    {
        $page = Page::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'landing',
        ]);

        $this->site->update([
            'settings' => [
                'homepage_type' => 'page',
                'homepage_id' => $page->id,
            ],
        ]);

        $response = $this->actingAsOwner()
            ->get('/sites/mysite');

        $response->assertStatus(200);
        $response->assertSee('landing', false);
    }

    public function test_post_without_category_uses_uncategorized(): void
    {
        Post::factory()->published()->create([
            'site_id' => $this->site->id,
            'slug' => 'orphan-post',
            'category_id' => null,
        ]);

        $this->actingAsOwner()
            ->get('/sites/mysite/uncategorized/orphan-post')
            ->assertStatus(200);
    }
}
