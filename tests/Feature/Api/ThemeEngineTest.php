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
        // Mark the site theme as system to test the guard. is_system is not
        // mass-assignable (RLS injection guard), so set it explicitly.
        $this->theme->forceFill(['is_system' => true])->save();

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

    // ── T1.2 hardening ──────────────────────────────────────────────

    public function test_site_wide_assign_flags_all_pages_stale(): void
    {
        $p1 = \App\Models\Page::factory()->published()->create(['site_id' => $this->site->id]);
        $p2 = \App\Models\Page::factory()->published()->create(['site_id' => $this->site->id]);
        $draft = \App\Models\Page::factory()->create(['site_id' => $this->site->id, 'status' => 'draft']);
        $other = Theme::create([
            'site_id' => $this->site->id, 'name' => 'Other', 'slug' => 'other',
            'version' => '1.0.0', 'config' => [], 'manifest_json' => [], 'template_path' => '',
            'document' => ['$metadata' => ['name' => 'Other']], 'modes' => ['light'], 'schema_version' => '1.0.0',
        ]);

        $resp = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/assign", ['theme_id' => $other->id])
            ->assertStatus(200);

        $resp->assertJsonPath('meta.stale.site_wide', true);
        $this->assertGreaterThanOrEqual(2, $resp->json('meta.stale.pages'));
        $this->assertTrue($p1->fresh()->needs_republish);
        $this->assertTrue($p2->fresh()->needs_republish);
        $this->assertFalse($draft->fresh()->needs_republish, 'drafts are not published output');
        $this->assertSame($other->id, $this->site->fresh()->active_theme_id);
    }

    public function test_fork_does_not_switch_active_theme_by_default(): void
    {
        $original = $this->site->active_theme_id;
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}/fork", ['name' => 'Experiment'])
            ->assertStatus(201);
        $this->assertSame($original, $this->site->fresh()->active_theme_id, 'fork must not silently re-theme the live site');
    }

    public function test_fork_with_activate_switches_and_flags_stale(): void
    {
        $page = \App\Models\Page::factory()->published()->create(['site_id' => $this->site->id]);
        $resp = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}/fork?activate=1", ['name' => 'Live Fork'])
            ->assertStatus(201);
        $forkId = $resp->json('data.id');
        $this->assertSame($forkId, $this->site->fresh()->active_theme_id);
        $this->assertTrue($page->fresh()->needs_republish);
        $resp->assertJsonPath('meta.stale.site_wide', true);
    }

    public function test_export_import_round_trips_identically(): void
    {
        // capture the exported bundle
        $bundle = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/theme-engine/themes/{$this->theme->id}/export")
            ->assertStatus(200)
            ->assertJsonPath('stillopress_theme', '1.0')
            ->json();

        $originalDoc = $this->theme->document;

        // delete the source theme, then re-import the bundle
        $this->theme->delete();
        $imported = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/import", ['bundle' => $bundle])
            ->assertStatus(201)
            ->json('data');

        $reimported = Theme::find($imported['id']);
        // jsonb does not preserve key order, so compare order-independently
        $this->assertEqualsCanonicalizing($originalDoc, $reimported->document, 'token document must survive the round-trip');
        $this->assertSame('Test Theme', $reimported->name);
        $this->assertFalse((bool) $reimported->is_system, 'imported theme is site-owned, never system');
    }

    public function test_legacy_bare_document_import_still_works(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/import", [
                'document' => ['$metadata' => ['name' => 'Legacy', 'modes' => ['light']]],
                'name' => 'Legacy Import',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Legacy Import');
    }

    public function test_is_system_is_not_mass_assignable(): void
    {
        // even if a payload smuggles is_system, import must produce a site theme
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-engine/import", [
                'bundle' => [
                    'stillopress_theme' => '1.0',
                    'metadata' => ['name' => 'Sneaky', 'modes' => ['light']],
                    'document' => ['$metadata' => ['name' => 'Sneaky']],
                    'is_system' => true,
                    'site_id' => null,
                ],
            ])
            ->assertStatus(201);
        $sneaky = Theme::where('name', 'Sneaky')->first();
        $this->assertFalse((bool) $sneaky->is_system);
        $this->assertSame($this->site->id, $sneaky->site_id);
    }

    public function test_themeless_site_still_emits_default_tokens(): void
    {
        $bare = Site::factory()->create(['tenant_id' => $this->tenant->id, 'active_theme_id' => null]);
        $css = app(\App\Domain\Theme\Services\DesignTokenGenerator::class)->generate($bare);
        $this->assertNotSame('', $css);
        $this->assertStringContainsString('--color-primary', $css);
        $this->assertStringContainsString('--space-4', $css);
    }
}
