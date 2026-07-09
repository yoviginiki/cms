<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class PostTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/sites/{$this->site->id}/posts{$suffix}";
    }

    public function test_can_list_posts(): void
    {
        Post::factory()->count(3)->create(['site_id' => $this->site->id, 'category_id' => null]);

        $this->actingAsOwner()->getJson($this->url(), $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_post(): void
    {
        $this->actingAsOwner()->postJson($this->url(), [
            'title' => 'Hello Post',
        ], $this->apiHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Hello Post');
    }

    public function test_can_filter_posts_by_status(): void
    {
        Post::factory()->published()->create(['site_id' => $this->site->id, 'category_id' => null]);
        Post::factory()->create(['site_id' => $this->site->id, 'status' => 'draft', 'category_id' => null]);

        $this->actingAsOwner()->getJson($this->url('?status=published'), $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_posts_by_category(): void
    {
        $cat = Category::factory()->create(['site_id' => $this->site->id]);
        Post::factory()->create(['site_id' => $this->site->id, 'category_id' => $cat->id]);
        Post::factory()->create(['site_id' => $this->site->id, 'category_id' => null]);

        $this->actingAsOwner()->getJson($this->url("?category_id={$cat->id}"), $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_assign_category_to_post(): void
    {
        $cat = Category::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()->postJson($this->url(), [
            'title' => 'Categorized',
            'category_id' => $cat->id,
        ], $this->apiHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.category_id', $cat->id);
    }
}
