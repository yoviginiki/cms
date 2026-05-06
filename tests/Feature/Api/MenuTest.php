<?php

namespace Tests\Feature\Api;

use App\Models\Menu;
use App\Models\Site;
use Tests\TestCase;

class MenuTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeMenu(array $attrs = []): Menu
    {
        $name = $attrs['name'] ?? 'Nav';
        return Menu::create(array_merge([
            'site_id' => $this->site->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::random(4),
        ], $attrs));
    }

    public function test_can_list_menus(): void
    {
        $this->makeMenu();
        $this->makeMenu(['name' => 'Footer']);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/menus")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_menu(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/menus", [
                'name' => 'Main Nav',
                'location' => 'header',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Main Nav')
            ->assertJsonPath('data.location', 'header');

        $this->assertDatabaseHas('menus', ['name' => 'Main Nav', 'site_id' => $this->site->id]);
    }

    public function test_can_show_menu_with_item_tree(): void
    {
        $menu = $this->makeMenu();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['menu', 'items']]);
    }

    public function test_can_update_menu(): void
    {
        $menu = $this->makeMenu();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}", [
                'name' => 'Footer Nav',
                'location' => 'footer',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Footer Nav');
    }

    public function test_can_delete_menu(): void
    {
        $menu = $this->makeMenu();

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_can_sync_menu_items(): void
    {
        $menu = $this->makeMenu();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}/items", [
                'items' => [
                    ['label' => 'Home', 'url' => '/', 'sort_order' => 0],
                    ['label' => 'About', 'url' => '/about', 'sort_order' => 1],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_items', ['menu_id' => $menu->id, 'label' => 'Home']);
        $this->assertDatabaseHas('menu_items', ['menu_id' => $menu->id, 'label' => 'About']);
    }

    public function test_editor_cannot_delete_menu(): void
    {
        $menu = $this->makeMenu();

        $this->actingAsEditor()
            ->deleteJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}")
            ->assertStatus(403);
    }
}
