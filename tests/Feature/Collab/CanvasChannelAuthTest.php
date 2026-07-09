<?php

namespace Tests\Feature\Collab;

use App\Domain\Collab\CanvasChannelAuthorizer;
use App\Models\Page;
use App\Models\User;
use Tests\TestCase;

/**
 * Phase 0 security gate for collaborative editing: only tenant members who can
 * update the page may join its presence channel. Verified without a running
 * Reverb server by testing the extracted authorizer directly.
 */
class CanvasChannelAuthTest extends TestCase
{
    private function page(): Page
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);

        return Page::where('site_id', $site->id)->firstOrFail();
    }

    public function test_owner_joins_with_a_safe_member_payload(): void
    {
        $page = $this->page();
        $member = app(CanvasChannelAuthorizer::class)->authorize($this->owner, $page->id);

        $this->assertIsArray($member);
        $this->assertSame((string) $this->owner->id, $member['id']);
        $this->assertSame($this->owner->name, $member['name']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $member['color']);
        $this->assertArrayNotHasKey('email', $member); // no PII leaks into presence
        $this->assertArrayNotHasKey('role', $member);
    }

    public function test_same_tenant_editor_may_join(): void
    {
        $page = $this->page();
        $editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);

        $this->assertIsArray(app(CanvasChannelAuthorizer::class)->authorize($editor, $page->id));
    }

    public function test_cross_tenant_user_is_rejected(): void
    {
        $page = $this->page(); // in the owner's tenant

        // a user in a DIFFERENT tenant, with their own tenant's RLS scope active
        $other = User::factory()->owner()->create();
        $this->setTenantScope($other);

        $this->assertFalse(app(CanvasChannelAuthorizer::class)->authorize($other, $page->id));
    }
}
