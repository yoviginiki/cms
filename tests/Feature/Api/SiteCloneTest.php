<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use Tests\TestCase;

class SiteCloneTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_clone_site(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/clone", [
                'name' => 'Cloned Site',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('sites', ['name' => 'Cloned Site']);
    }

    public function test_can_export_site(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/export")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_clone_requires_name(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/clone", [])
            ->assertStatus(422);
    }

    public function test_editor_cannot_clone(): void
    {
        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/clone", [
                'name' => 'Cloned',
            ])
            ->assertStatus(403);
    }
}
