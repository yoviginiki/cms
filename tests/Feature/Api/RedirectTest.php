<?php

namespace Tests\Feature\Api;

use App\Models\Redirect;
use App\Models\Site;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_redirects(): void
    {
        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/old', 'target_url' => '/new', 'status_code' => 301]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/redirects")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_redirect(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/old-page',
                'target_url' => '/new-page',
                'status_code' => 301,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.source_path', '/old-page')
            ->assertJsonPath('data.status_code', 301);

        $this->assertDatabaseHas('redirects', ['source_path' => '/old-page', 'site_id' => $this->site->id]);
    }

    public function test_defaults_to_301_when_status_code_omitted(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/a',
                'target_url' => '/b',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('redirects', ['source_path' => '/a', 'status_code' => 301]);
    }

    public function test_can_update_redirect(): void
    {
        $redirect = Redirect::create([
            'site_id' => $this->site->id,
            'source_path' => '/old',
            'target_url' => '/new',
            'status_code' => 301,
        ]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/redirects/{$redirect->id}", [
                'status_code' => 302,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status_code', 302);
    }

    public function test_can_delete_redirect(): void
    {
        $redirect = Redirect::create([
            'site_id' => $this->site->id,
            'source_path' => '/gone',
            'target_url' => '/here',
            'status_code' => 301,
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/redirects/{$redirect->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }

    public function test_rejects_invalid_status_code(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/x',
                'target_url' => '/y',
                'status_code' => 200,
            ])
            ->assertStatus(422);
    }

    public function test_editor_cannot_create_redirect(): void
    {
        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/x',
                'target_url' => '/y',
            ])
            ->assertStatus(403);
    }
}
