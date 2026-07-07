<?php

namespace Tests\Feature\Security;

use App\Models\Site;
use Tests\TestCase;

/**
 * FIX-A2a — write endpoints that previously relied only on the tenant-checked
 * Site binding must now enforce a role gate (authorize('update', $site)).
 */
class ControllerAuthorizationTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_editor_cannot_create_magazine_style(): void
    {
        $editor = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'editor']);

        $response = $this->actingAs($editor, 'sanctum')->postJson(
            "/api/v1/sites/{$this->site->id}/magazine-styles",
            ['name' => 'Injected', 'type' => 'paragraph', 'properties' => []],
            $this->apiHeaders(),
        );

        $response->assertForbidden();
    }

    public function test_editor_cannot_create_block_template(): void
    {
        $editor = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'editor']);

        $response = $this->actingAs($editor, 'sanctum')->postJson(
            "/api/v1/sites/{$this->site->id}/block-templates",
            ['name' => 'Injected', 'blocks_data' => [['type' => 'text', 'data' => []]]],
            $this->apiHeaders(),
        );

        $response->assertForbidden();
    }
}
