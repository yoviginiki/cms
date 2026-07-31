<?php

namespace Tests\Feature\InlineEdit;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * HTTP-level RBAC + RLS for the inline endpoints (Phase 4.3 / 4.4).
 *
 * CI-ready; NOT run in this session (no test DB). The role × action matrix and
 * tenant guard are proven DB-free in
 * tests/Unit/InlineEdit/PageInlinePolicyTest.php.
 */
final class InlineEditRbacTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        Block::factory()->create([
            'blockable_id' => $this->page->id, 'blockable_type' => 'page',
            'type' => 'heading', 'data' => ['text' => 'Hi', 'level' => 'h1'], 'order' => 0,
        ]);
    }

    private function sessionUrl(): string
    {
        return "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline/session";
    }

    private function actingRole(string $role): self
    {
        $u = $role === 'owner' ? $this->owner
            : User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $role]);
        $this->actingAs($u, 'sanctum');
        return $this;
    }

    public function test_viewer_and_author_cannot_open_inline_session(): void
    {
        foreach (['viewer', 'author'] as $role) {
            $this->actingRole($role)
                ->postJson($this->sessionUrl(), [], $this->apiHeaders())
                ->assertStatus(403);
        }
    }

    public function test_editor_admin_owner_can_open_inline_session(): void
    {
        foreach (['editor', 'admin', 'owner'] as $role) {
            $this->actingRole($role)
                ->postJson($this->sessionUrl(), [], $this->apiHeaders())
                ->assertOk();
        }
    }

    public function test_viewer_preview_has_no_edit_overlay(): void
    {
        // Even with ?sp_edit=1, a viewer's preview renders in Publish mode.
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'viewer']);
        $res = $this->actingAs($viewer)
            ->get("/sites/{$this->site->slug}/{$this->page->slug}?sp_edit=1");
        $res->assertOk();
        $this->assertStringNotContainsString('data-sp-block', $res->getContent());
        $this->assertStringNotContainsString('/inline-edit/overlay.js', $res->getContent());
    }

    // ---- RLS / tenant boundary (4.4) ---------------------------------------

    public function test_forged_site_from_another_tenant_is_blocked(): void
    {
        // A second tenant with its own site + editor.
        $otherTenant = Tenant::factory()->create();
        $otherSite = Site::factory()->create(['tenant_id' => $otherTenant->id]);
        $intruder = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'editor']);

        // Intruder points their OWN site id at OUR page id — must not cross over.
        $this->actingAs($intruder, 'sanctum');
        $this->setTenantScope($intruder);
        $crossed = $this->postJson(
            "/api/v1/sites/{$otherSite->id}/pages/{$this->page->id}/inline/session",
            [], $this->apiHeaders()
        );
        // Page is invisible under the intruder's RLS context (404) or denied by
        // the sameTenant policy (403) — either way, no cross-tenant access.
        $this->assertContains($crossed->status(), [403, 404]);

        // Intruder points at OUR site id directly — Site binding is tenant-scoped → 404.
        $this->postJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline/session",
            [], $this->apiHeaders()
        )->assertStatus(404);
    }
}
