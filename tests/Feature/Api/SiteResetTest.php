<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class SiteResetTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_preview_reset(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/reset/preview")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['pages', 'posts', 'categories', 'tags', 'assets', 'menus']]);
    }

    public function test_reset_requires_confirmation(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/reset/content", [
                'confirm' => 'wrong name',
                'options' => ['pages' => true],
            ])
            ->assertStatus(422);
    }

    public function test_can_reset_content(): void
    {
        Page::factory()->published()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/reset/content", [
                'confirm' => $this->site->name,
                'options' => ['pages' => true],
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['message', 'deleted', 'remaining']]);

        $this->assertEquals(0, $this->site->pages()->count());
    }

    public function test_factory_reset_requires_exact_confirmation(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/reset/factory", [
                'confirm' => 'wrong',
            ])
            ->assertStatus(422);
    }

    public function test_can_factory_reset(): void
    {
        Page::factory()->published()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/reset/factory", [
                'confirm' => 'FACTORY RESET ' . $this->site->name,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.message', 'Factory reset complete. Site is now empty.');
    }

    public function test_editor_cannot_reset(): void
    {
        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/reset/content", [
                'confirm' => $this->site->name,
                'options' => ['pages' => true],
            ])
            ->assertStatus(403);
    }
}
