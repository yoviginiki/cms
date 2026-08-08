<?php

namespace Tests\Feature\Modules;

use App\Models\Module;
use App\Models\ModuleToken;
use Tests\TestCase;

class ModuleSettingsApiTest extends TestCase
{
    private function seedModule(bool $global = false): Module
    {
        return Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'description' => 'Receives cultural bulletins.',
            'enabled_globally' => $global,
            'settings_schema' => ['fields' => []],
        ]);
    }

    public function test_owner_index_exposes_all_abilities_and_settings(): void
    {
        $this->seedModule(global: true);

        $response = $this->actingAsOwner()->getJson('/api/v1/modules', $this->apiHeaders());

        $response->assertOk()
            ->assertJsonPath('abilities.use', true)
            ->assertJsonPath('abilities.manage', true)
            ->assertJsonPath('abilities.administer', true)
            ->assertJsonPath('modules.0.key', 'culture-engine');

        $this->assertArrayHasKey('settings_schema', $response->json('modules.0'));
    }

    public function test_editor_index_gates_abilities_and_hides_settings(): void
    {
        $this->seedModule(global: true);

        $response = $this->actingAsEditor()->getJson('/api/v1/modules', $this->apiHeaders());

        $response->assertOk()
            ->assertJsonPath('abilities.use', true)
            ->assertJsonPath('abilities.manage', false)
            ->assertJsonPath('abilities.administer', false);

        $this->assertArrayNotHasKey('settings_schema', $response->json('modules.0'));
    }

    public function test_only_owner_can_toggle_global(): void
    {
        $this->seedModule();

        $this->actingAsAdmin()
            ->patchJson('/api/v1/modules/culture-engine/global', ['enabled' => true], $this->apiHeaders())
            ->assertStatus(403);

        $this->actingAsOwner()
            ->patchJson('/api/v1/modules/culture-engine/global', ['enabled' => true], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('enabled_globally', true);

        $this->assertTrue(Module::where('key', 'culture-engine')->value('enabled_globally'));
    }

    public function test_tenant_toggle_requires_manage_and_global_enabled(): void
    {
        $this->seedModule(global: false);

        // editor cannot manage
        $this->actingAsEditor()
            ->patchJson('/api/v1/modules/culture-engine/tenant', ['enabled' => true], $this->apiHeaders())
            ->assertStatus(403);

        // admin can, but not while globally disabled
        $this->actingAsAdmin()
            ->patchJson('/api/v1/modules/culture-engine/tenant', ['enabled' => true], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error', 'module_not_globally_enabled');

        // enable globally, then the tenant toggle succeeds
        Module::where('key', 'culture-engine')->update(['enabled_globally' => true]);

        $this->actingAsAdmin()
            ->patchJson('/api/v1/modules/culture-engine/tenant', ['enabled' => true], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('enabled_for_tenant', true);
    }

    public function test_token_lifecycle(): void
    {
        $this->seedModule(global: true);

        // create → plaintext returned once
        $create = $this->actingAsAdmin()
            ->postJson('/api/v1/modules/culture-engine/tokens', ['name' => 'Engine box'], $this->apiHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('token.name', 'Engine box')
            ->assertJsonStructure(['token' => ['id', 'name', 'abilities'], 'plaintext']);

        $plaintext = $create->json('plaintext');
        $tokenId = $create->json('token.id');
        $this->assertStringStartsWith('mod_', $plaintext);

        // list → metadata only, never the hash
        $list = $this->actingAsAdmin()
            ->getJson('/api/v1/modules/culture-engine/tokens', $this->apiHeaders());
        $list->assertOk()->assertJsonPath('tokens.0.name', 'Engine box');
        $this->assertArrayNotHasKey('token_hash', $list->json('tokens.0'));

        // revoke
        $this->actingAsAdmin()
            ->deleteJson("/api/v1/modules/culture-engine/tokens/{$tokenId}", [], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('revoked', true);

        $this->setTenantScope($this->owner);
        $this->assertNotNull(ModuleToken::find($tokenId)->revoked_at);
    }

    public function test_editor_cannot_create_tokens(): void
    {
        $this->seedModule(global: true);

        $this->actingAsEditor()
            ->postJson('/api/v1/modules/culture-engine/tokens', ['name' => 'x'], $this->apiHeaders())
            ->assertStatus(403);
    }
}
