<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class EditorPresenceTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
    }

    public function test_can_send_heartbeat_for_page(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/editor/heartbeat', ['page_id' => $this->page->id])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('active_editors', [
            'user_id' => $this->owner->id,
            'page_id' => $this->page->id,
        ]);
    }

    public function test_heartbeat_requires_page_or_post_id(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/editor/heartbeat', [])
            ->assertStatus(422);
    }

    public function test_can_get_presence_for_page(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/editor/presence/pages/{$this->page->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_cannot_send_heartbeat(): void
    {
        $this->postJson('/api/v1/editor/heartbeat', ['page_id' => $this->page->id])
            ->assertStatus(401);
    }
}
