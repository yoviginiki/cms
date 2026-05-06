<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Site;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_categories(): void
    {
        Category::factory()->count(3)->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/categories")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_category(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/categories", [
                'name' => 'Tech',
                'slug' => 'tech',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Tech');

        $this->assertDatabaseHas('categories', ['name' => 'Tech', 'site_id' => $this->site->id]);
    }

    public function test_can_create_child_category(): void
    {
        $parent = Category::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/categories", [
                'name' => 'Sub',
                'slug' => 'sub',
                'parent_id' => $parent->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/categories/{$category->id}", [
                'name' => 'Updated',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/categories/{$category->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_can_reorder_categories(): void
    {
        $a = Category::factory()->create(['site_id' => $this->site->id, 'sort_order' => 0]);
        $b = Category::factory()->create(['site_id' => $this->site->id, 'sort_order' => 1]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/categories/reorder", [
                'items' => [
                    ['id' => $b->id, 'sort_order' => 0],
                    ['id' => $a->id, 'sort_order' => 1],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('categories', ['id' => $b->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('categories', ['id' => $a->id, 'sort_order' => 1]);
    }

    public function test_editor_cannot_delete_category(): void
    {
        $category = Category::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsEditor()
            ->deleteJson("/api/v1/sites/{$this->site->id}/categories/{$category->id}")
            ->assertStatus(403);
    }
}
