<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_get_analytics_dashboard(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/analytics")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_views', 'views_per_day', 'top_pages']]);
    }

    public function test_dashboard_accepts_days_parameter(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/analytics?days=7")
            ->assertStatus(200);
    }

    public function test_track_endpoint_is_public(): void
    {
        $this->postJson("/api/v1/sites/{$this->site->id}/t", [
            'path' => '/home',
            'referrer' => null,
        ])->assertStatus(200);
    }

    public function test_editor_can_view_analytics(): void
    {
        $this->actingAsEditor()
            ->getJson("/api/v1/sites/{$this->site->id}/analytics")
            ->assertStatus(200);
    }
}
