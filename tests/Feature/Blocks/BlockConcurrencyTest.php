<?php

namespace Tests\Feature\Blocks;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * FIX-C11a — opt-in optimistic concurrency on the block save endpoint. A stale
 * expected_version is rejected with 409; omitting it keeps the old behaviour.
 */
class BlockConcurrencyTest extends TestCase
{
    public function test_stale_expected_version_is_rejected_absent_is_allowed(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id]);

        $blocks = ['blocks' => [['type' => 'text', 'data' => ['content' => 'hi'], 'order' => 0]]];

        // Load -> capture version
        $version = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$site->id}/pages/{$page->id}/blocks", $this->apiHeaders())
            ->assertOk()->json('version');

        // Save with the correct version -> OK, returns a new version
        $newVersion = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$site->id}/pages/{$page->id}/blocks",
            $blocks + ['expected_version' => $version],
            $this->apiHeaders(),
        )->assertOk()->json('version');

        $this->assertNotSame($version, $newVersion);

        // Save again with the STALE version -> 409
        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$site->id}/pages/{$page->id}/blocks",
            $blocks + ['expected_version' => $version],
            $this->apiHeaders(),
        )->assertStatus(409);

        // Save without a version -> allowed (backwards compatible)
        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$site->id}/pages/{$page->id}/blocks",
            $blocks,
            $this->apiHeaders(),
        )->assertOk();
    }
}
