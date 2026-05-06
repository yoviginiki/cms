<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use App\Models\Theme;
use Tests\TestCase;

class ThemeEngineTest extends TestCase
{
    private Site $site;
    private Theme $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->theme = Theme::create([
            'site_id' => $this->site->id,
            'name' => 'Test Theme',
            'slug' => 'test-theme',
            'version' => '1.0.0',
            'config' => [],
            'manifest_json' => [],
            'template_path' => '',
            'document' => [
                '$metadata' => ['name' => 'Test', 'version' => '1.0.0', 'modes' => ['light']],
                'primitive' => ['color' => ['blue' => ['500' => ['$type' => 'color', '$value' => '#3B82F6']]]],
                'semantic' => ['color' => ['brand' => ['$type' => 'color', '$value' => '{primitive.color.blue.500}']]],
            ],
            'modes' => ['light'],
            'schema_version' => '1.0.0',
            'is_system' => false,
        ]);
        $this->site->update(['active_theme_id' => $this->theme->id]);
    }

    public function test_can_list_themes(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/themes")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_show_theme(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Test Theme');
    }

    public function test_can_update_non_system_theme(): void
    {
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}", [
                'name' => 'Updated Theme',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Theme');
    }

    public function test_cannot_update_system_theme(): void
    {
        // Mark the site theme as system to test the guard
        $this->theme->update(['is_system' => true]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}", [
                'name' => 'Hacked',
            ])
            ->assertStatus(403);
    }

    public function test_can_fork_theme(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}/fork", [
                'name' => 'My Fork',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'My Fork');
    }

    public function test_can_assign_theme(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/assign", [
                'theme_id' => $this->theme->id,
                'mode' => 'light',
            ])
            ->assertStatus(200);
    }

    public function test_can_import_theme(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/import", [
                'document' => [
                    '$metadata' => ['name' => 'Imported', 'modes' => ['light', 'dark']],
                    'primitive' => [],
                    'semantic' => [],
                ],
                'name' => 'Imported Theme',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Imported Theme');
    }

    public function test_can_export_theme(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}/export")
            ->assertStatus(200);
    }

    public function test_can_save_overrides(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/overrides", [
                'scope' => 'site',
                'mode' => 'light',
                'overrides' => [
                    ['token_path' => 'semantic.color.brand', 'value' => '#FF0000'],
                ],
            ])
            ->assertStatus(200);
    }

    public function test_can_get_versions(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/versions")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_resolve_theme(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/resolve?mode=light")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens', 'css', 'hash', 'count']]);
    }
}
