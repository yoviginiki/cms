<?php

namespace Tests\Feature\Api;

use App\Models\Magazine;
use App\Models\Site;
use Illuminate\Support\Str;
use Tests\TestCase;

class MagazineTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeMagazine(array $attrs = []): Magazine
    {
        return Magazine::create(array_merge([
            'site_id' => $this->site->id,
            'title' => 'Test Magazine',
            'slug' => Str::slug('Test Magazine') . '-' . Str::random(4),
            'status' => 'draft',
        ], $attrs));
    }

    public function test_can_list_magazines(): void
    {
        $this->makeMagazine();
        $this->makeMagazine(['title' => 'Issue 2']);
        $this->makeMagazine(['title' => 'Issue 3']);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazines")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_magazine(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/magazines", [
                'title' => 'Issue 01',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Issue 01');

        $this->assertDatabaseHas('magazines', ['title' => 'Issue 01', 'site_id' => $this->site->id]);
    }

    public function test_can_show_magazine(): void
    {
        $magazine = $this->makeMagazine();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazines/{$magazine->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $magazine->id);
    }

    public function test_can_update_magazine(): void
    {
        $magazine = $this->makeMagazine();

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/magazines/{$magazine->id}", [
                'title' => 'Updated Issue',
                'status' => 'published',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Issue');
    }

    public function test_can_delete_magazine(): void
    {
        $magazine = $this->makeMagazine();

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/magazines/{$magazine->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('magazines', ['id' => $magazine->id]);
    }

    public function test_can_search_magazines(): void
    {
        $this->makeMagazine(['title' => 'Spring Edition', 'slug' => 'spring-edition']);
        $this->makeMagazine(['title' => 'Winter Edition', 'slug' => 'winter-edition']);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazines?search=Spring")
            ->assertStatus(200);

        $titles = collect($response->json('data.data') ?? $response->json('data'))->pluck('title');
        $this->assertContains('Spring Edition', $titles);
        $this->assertNotContains('Winter Edition', $titles);
    }

    public function test_editor_cannot_create_magazine(): void
    {
        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/magazines", ['title' => 'Sneaky'])
            ->assertStatus(403);
    }

    public function test_editor_cannot_update_magazine(): void
    {
        $magazine = $this->makeMagazine();

        $this->actingAsEditor()
            ->putJson("/api/v1/sites/{$this->site->id}/magazines/{$magazine->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_editor_cannot_delete_magazine(): void
    {
        $magazine = $this->makeMagazine();

        $this->actingAsEditor()
            ->deleteJson("/api/v1/sites/{$this->site->id}/magazines/{$magazine->id}")
            ->assertStatus(403);
    }
}
