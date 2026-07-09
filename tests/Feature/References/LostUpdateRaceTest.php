<?php

namespace Tests\Feature\References;

use App\Domain\References\Services\StalenessResolver;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * FIX §7 D2 — a delta batch must not clear needs_republish for an item that was
 * re-flagged after the build (else the newer staleness is silently lost).
 */
class LostUpdateRaceTest extends TestCase
{
    public function test_clear_skips_items_re_flagged_after_the_build(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $resolver = app(StalenessResolver::class);

        // Page A: flagged, built, NOT re-flagged -> should clear.
        $a = Page::factory()->create(['site_id' => $site->id, 'needs_republish' => true, 'needs_republish_reason' => 'x']);
        $stampA = $a->fresh()->updated_at->toIso8601String();

        // Page B: flagged, built, then RE-FLAGGED after the build snapshot.
        $b = Page::factory()->create(['site_id' => $site->id, 'needs_republish' => true, 'needs_republish_reason' => 'x']);
        $stampB = $b->fresh()->updated_at->toIso8601String();

        // Simulate a re-flag of B arriving after the build (bumps updated_at).
        $this->travel(1)->second();
        Page::where('id', $b->id)->update(['needs_republish' => true, 'needs_republish_reason' => 'newer change']);

        $built = [
            ['type' => 'page', 'id' => $a->id, 'stamp' => $stampA],
            ['type' => 'page', 'id' => $b->id, 'stamp' => $stampB],
        ];
        $resolver->clearBuiltIfUnchanged($built);

        $this->assertFalse($a->fresh()->needs_republish, 'unchanged page should be cleared');
        $this->assertTrue($b->fresh()->needs_republish, 're-flagged page must NOT be cleared (lost update)');
    }
}
