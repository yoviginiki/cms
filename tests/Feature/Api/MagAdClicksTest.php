<?php

namespace Tests\Feature\Api;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MagAdClicksTest extends TestCase
{
    private Site $site;
    private MagazineIssue $issue;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.magazine_dtp_designer_enabled' => true]);
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->issue = MagazineIssue::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'title' => 'Ad Test',
            'status' => 'published',
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_public_beacon_records_and_report_aggregates(): void
    {
        // unauthenticated beacon (sendBeacon posts raw JSON)
        $payload = json_encode(['issue' => $this->issue->id, 'href' => 'https://sponsor.example/offer']);
        $this->call('POST', '/api/v1/mag-metric', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $payload)->assertNoContent();
        $this->call('POST', '/api/v1/mag-metric', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $payload)->assertNoContent();

        $this->setTenantScope($this->owner);
        $this->assertSame(2, DB::table('mag_ad_clicks')->where('issue_id', $this->issue->id)->count());

        $res = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$this->issue->id}/dtp-ad-clicks")
            ->assertOk()->json('data');
        $this->assertCount(1, $res);
        $this->assertSame(2, (int) $res[0]['clicks']);
    }

    public function test_beacon_rejects_garbage_silently(): void
    {
        $this->call('POST', '/api/v1/mag-metric', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'not json')->assertNoContent();
        $this->call('POST', '/api/v1/mag-metric', [], [], [], ['CONTENT_TYPE' => 'text/plain'],
            json_encode(['issue' => 'nope', 'href' => 'javascript:x']))->assertNoContent();
        $this->setTenantScope($this->owner);
        $this->assertSame(0, DB::table('mag_ad_clicks')->count());
    }
}
