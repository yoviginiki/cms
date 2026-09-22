<?php

namespace Tests\Feature\Security;

use App\Models\Deployment;
use App\Models\Site;
use App\Models\ThemeTemplate;
use App\Models\User;
use Tests\TestCase;

/**
 * F07 (audit 2026-09-22) — template block sync had no role gate (any
 * authenticated tenant user could push blocks into a theme template), and
 * several nested resources were not tied to the {site} in the URL.
 */
class TemplateAuthorizationTest extends TestCase
{
    private Site $site;
    private ThemeTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->template = ThemeTemplate::create([
            'site_id' => $this->site->id, 'name' => 'Single', 'slug' => 'single', 'type' => 'post',
            'created_by' => $this->owner->id,
        ]);
    }

    private function as(string $role): self
    {
        $u = $role === 'owner' ? $this->owner : User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $role]);
        $this->actingAs($u, 'sanctum');
        return $this;
    }

    public function test_template_blocks_sync_matrix(): void
    {
        $payload = ['overwrite' => true, 'blocks' => [['type' => 'heading', 'order' => 0, 'data' => ['text' => 'T', 'level' => 'h1']]]];
        $url = "/api/v1/sites/{$this->site->id}/templates/{$this->template->id}/blocks";

        foreach (['viewer', 'author', 'editor'] as $role) {
            $this->as($role)->putJson($url, $payload, $this->apiHeaders())->assertForbidden();
        }
        foreach (['admin', 'owner'] as $role) {
            $this->as($role)->putJson($url, $payload, $this->apiHeaders())->assertOk();
        }
        // reading stays open to every tenant member
        $this->as('viewer')->getJson($url, $this->apiHeaders())->assertOk();
    }

    public function test_template_crud_matrix(): void
    {
        $base = "/api/v1/sites/{$this->site->id}/templates";
        foreach (['viewer', 'author', 'editor'] as $role) {
            $this->as($role)->postJson($base, ['name' => 'X', 'type' => 'post'], $this->apiHeaders())->assertForbidden();
            $this->as($role)->putJson("{$base}/{$this->template->id}", ['name' => 'Y'], $this->apiHeaders())->assertForbidden();
            $this->as($role)->deleteJson("{$base}/{$this->template->id}", [], $this->apiHeaders())->assertForbidden();
        }
        $this->as('admin')->putJson("{$base}/{$this->template->id}", ['name' => 'Renamed'], $this->apiHeaders())->assertOk();
        $this->assertSame('Renamed', $this->template->fresh()->name);
    }

    public function test_nested_resources_must_belong_to_the_site_in_the_url(): void
    {
        $siteB = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $templateB = ThemeTemplate::create([
            'site_id' => $siteB->id, 'name' => 'B', 'slug' => 'b', 'type' => 'post', 'created_by' => $this->owner->id,
        ]);
        $depB = Deployment::create([
            'site_id' => $siteB->id, 'type' => 'full', 'status' => 'live', 'triggered_by' => $this->owner->id, 'metadata' => [],
        ]);

        $this->as('owner')
            ->putJson("/api/v1/sites/{$this->site->id}/templates/{$templateB->id}/blocks", ['overwrite' => true, 'blocks' => [['type' => 'heading', 'order' => 0, 'data' => ['text' => 'T', 'level' => 'h1']]]], $this->apiHeaders())
            ->assertNotFound();
        $this->as('owner')
            ->getJson("/api/v1/sites/{$this->site->id}/deployments/{$depB->id}", $this->apiHeaders())
            ->assertNotFound();
        $this->as('owner')
            ->postJson("/api/v1/sites/{$this->site->id}/deployments/{$depB->id}/rollback", [], $this->apiHeaders())
            ->assertNotFound();
        $this->assertSame(1, Deployment::where('site_id', $siteB->id)->count()); // no rollback deployment created
    }

    public function test_deployment_status_requires_site_view_permission(): void
    {
        $dep = Deployment::create([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => 'live', 'triggered_by' => $this->owner->id, 'metadata' => [],
        ]);
        $this->as('viewer')->getJson("/api/v1/sites/{$this->site->id}/deployments/{$dep->id}", $this->apiHeaders())->assertOk();

        $otherTenant = \App\Models\Tenant::factory()->create();
        $stranger = User::factory()->owner()->create(['tenant_id' => $otherTenant->id]);
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/sites/{$this->site->id}/deployments/{$dep->id}", $this->apiHeaders())
            ->assertNotFound();
    }
}
