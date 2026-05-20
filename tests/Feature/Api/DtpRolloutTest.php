<?php

namespace Tests\Feature\Api;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;
use App\Models\Site;
use Illuminate\Support\Str;
use Tests\TestCase;

class DtpRolloutTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeIssue(array $attrs = []): MagazineIssue
    {
        return MagazineIssue::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'title' => 'Test Issue',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ], $attrs));
    }

    private function addSpreadAndPage(MagazineIssue $issue): array
    {
        $spread = MagazineSpread::create([
            'issue_id' => $issue->id,
            'spread_index' => 0,
            'name' => 'Cover',
        ]);

        $page = MagazineDtpPage::create([
            'issue_id' => $issue->id,
            'spread_id' => $spread->id,
            'page_index' => 0,
            'side' => 'single',
            'width' => 595,
            'height' => 842,
        ]);

        return [$spread, $page];
    }

    private function addFrame(MagazineIssue $issue, MagazineDtpPage $page, array $attrs = []): MagazineFrame
    {
        return MagazineFrame::create(array_merge([
            'issue_id' => $issue->id,
            'page_id' => $page->id,
            'frame_type' => 'text',
            'name' => 'Test Frame',
            'x' => 40,
            'y' => 48,
            'width' => 200,
            'height' => 80,
            'rotation' => 0,
            'z_index' => 1,
            'visible' => true,
            'locked' => false,
            'content' => ['html' => '<p>Hello</p>'],
        ], $attrs));
    }

    // ─── Finding 1 & 2: Four-state model ───

    public function test_rollout_report_for_legacy_issue(): void
    {
        $issue = $this->makeIssue();

        config(['features.magazine_dtp_designer_enabled' => false]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('legacy', $data['status']);
        $this->assertFalse($data['canOpenDtp']);
        $this->assertFalse($data['canPromote']);
        $this->assertFalse($data['hasDtpData']);
    }

    public function test_rollout_report_when_feature_flag_off(): void
    {
        $issue = $this->makeIssue();
        $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => false]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('legacy', $data['status']);
        $this->assertFalse($data['canOpenDtp']);
        $this->assertNull($data['links']['dtpEditor']);
        $this->assertNull($data['links']['dtpPreview']);
        $this->assertContains('DTP Designer feature flag is disabled.', $data['blockingReasons']);
    }

    // ─── Finding 3: Frame-only data not ready ───

    public function test_frame_only_dtp_data_is_not_ready(): void
    {
        $issue = $this->makeIssue();

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Create frame WITHOUT spread/page — orphan frame
        MagazineFrame::create([
            'issue_id' => $issue->id,
            'frame_type' => 'text',
            'name' => 'Orphan Frame',
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 100,
            'rotation' => 0,
            'z_index' => 0,
            'visible' => true,
            'locked' => false,
            'content' => ['html' => '<p>Orphan</p>'],
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('legacy', $data['status']);
        $this->assertFalse($data['hasDtpData']);
        $this->assertFalse($data['capabilities']['hasDtpDocument']);
        $this->assertFalse($data['capabilities']['hasSpreadOrPage']);
    }

    // ─── Finding 1: dtp_beta with blocking errors ───

    public function test_dtp_data_with_preflight_errors_is_beta_not_ready(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Add image frame without src — triggers MISSING_IMAGE blocking error
        $this->addFrame($issue, $page, [
            'frame_type' => 'image',
            'name' => 'Missing Image',
            'content' => ['src' => ''],
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('dtp_beta', $data['status']);
        $this->assertFalse($data['canPromote']);
        $this->assertNotEmpty($data['blockingReasons']);
    }

    // ─── Finding 1: dtp_ready ───

    public function test_clean_dtp_data_is_ready(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Add valid text frame — no blocking errors
        $this->addFrame($issue, $page);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('dtp_ready', $data['status']);
        $this->assertTrue($data['canPromote']);
        $this->assertEmpty($data['blockingReasons']);
    }

    // ─── Finding 6: canPromote false/true ───

    public function test_can_promote_false_when_blocking_reasons_exist(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Trigger blocking error with javascript URL
        $this->addFrame($issue, $page, [
            'frame_type' => 'image',
            'content' => ['src' => 'javascript:alert(1)'],
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $this->assertFalse($response->json('data.canPromote'));
    }

    public function test_can_promote_true_when_ready(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => true]);

        $this->addFrame($issue, $page);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $this->assertTrue($response->json('data.canPromote'));
    }

    // ─── Finding 7: Legacy editor link always present ───

    public function test_legacy_editor_link_is_always_present(): void
    {
        $issue = $this->makeIssue();

        // Test with flag off
        config(['features.magazine_dtp_designer_enabled' => false]);
        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);
        $this->assertNotNull($response->json('data.links.legacyEditor'));
        $this->assertStringContainsString('/edit', $response->json('data.links.legacyEditor'));

        // Test with flag on
        config(['features.magazine_dtp_designer_enabled' => true]);
        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);
        $this->assertNotNull($response->json('data.links.legacyEditor'));
    }

    // ─── Finding 7: DTP editor link is SPA route ───

    public function test_dtp_editor_link_is_spa_route(): void
    {
        $issue = $this->makeIssue();

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $dtpEditorLink = $response->json('data.links.dtpEditor');
        $this->assertNotNull($dtpEditorLink);
        // Verify it points to the SPA route pattern (not an API route)
        $this->assertStringContainsString('/admin/sites/', $dtpEditorLink);
        $this->assertStringContainsString('/dtp-editor', $dtpEditorLink);
        $this->assertStringNotContainsString('/api/', $dtpEditorLink);
    }

    // ─── Finding 1: dtp_production documented as reserved ───

    public function test_dtp_production_state_not_returned_without_persisted_field(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $data = $response->json('data');
        // dtp_production is never returned without persisted editor_mode field
        $this->assertNotEquals('dtp_production', $data['status']);
        $this->assertFalse($data['capabilities']['productionStatePersisted']);
    }

    // ─── Finding 4: Capabilities object present ───

    public function test_capabilities_object_present_in_response(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        $this->assertIsArray($caps);
        $this->assertArrayHasKey('dtpFeatureEnabled', $caps);
        $this->assertArrayHasKey('hasDtpDocument', $caps);
        $this->assertArrayHasKey('hasSpreadOrPage', $caps);
        $this->assertArrayHasKey('previewLinkAvailable', $caps);
        $this->assertArrayHasKey('previewRenderable', $caps);
        $this->assertArrayHasKey('legacyFallbackAvailable', $caps);
        $this->assertArrayHasKey('productionStatePersisted', $caps);
        $this->assertTrue($caps['dtpFeatureEnabled']);
        $this->assertTrue($caps['hasDtpDocument']);
        $this->assertTrue($caps['hasSpreadOrPage']);
        $this->assertTrue($caps['previewLinkAvailable']);
        // previewRenderable checks render service + Blade view availability (MAG-P8)
        $this->assertTrue($caps['previewRenderable']);
        $this->assertTrue($caps['legacyFallbackAvailable']);
        $this->assertFalse($caps['productionStatePersisted']);
    }

    // ─── MAG-P8: Preview render health check ───

    public function test_preview_renderable_true_when_render_pipeline_available(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        $this->assertTrue($caps['previewLinkAvailable']);
        $this->assertTrue($caps['previewRenderable']);
    }

    public function test_preview_renderable_false_when_feature_flag_off(): void
    {
        $issue = $this->makeIssue();
        $this->addSpreadAndPage($issue);

        config(['features.magazine_dtp_designer_enabled' => false]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        $this->assertFalse($caps['previewLinkAvailable']);
        $this->assertFalse($caps['previewRenderable']);
    }

    public function test_preview_renderable_false_when_no_dtp_document(): void
    {
        $issue = $this->makeIssue();

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        // Link not available because no document
        $this->assertFalse($caps['previewLinkAvailable']);
        // Render not available because no document
        $this->assertFalse($caps['previewRenderable']);
    }

    public function test_preview_link_available_but_renderable_independent(): void
    {
        // This test proves the two fields are computed independently.
        // Both require feature flag + DTP document, but previewRenderable
        // additionally checks render service + Blade view existence.
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        // Both true because render pipeline is available in this app
        $this->assertTrue($caps['previewLinkAvailable']);
        $this->assertTrue($caps['previewRenderable']);
        // They are separate fields, not aliases
        $this->assertArrayHasKey('previewLinkAvailable', $caps);
        $this->assertArrayHasKey('previewRenderable', $caps);
    }

    public function test_preview_renderable_false_when_render_service_unresolvable(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Swap DtpRenderService binding to something unresolvable
        $this->app->bind(
            \App\Domain\Magazine\Services\DtpRenderService::class,
            fn () => throw new \RuntimeException('Render service unavailable'),
        );

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        // Link is available (feature flag + DTP doc)
        $this->assertTrue($caps['previewLinkAvailable']);
        // But render is NOT available (service unresolvable)
        $this->assertFalse($caps['previewRenderable']);
    }

    public function test_preview_renderable_false_when_blade_view_missing(): void
    {
        $issue = $this->makeIssue();
        [$spread, $page] = $this->addSpreadAndPage($issue);
        $this->addFrame($issue, $page);

        config(['features.magazine_dtp_designer_enabled' => true]);

        // Mock view factory to report dtp-preview as missing
        $viewFactory = \Mockery::mock($this->app->make('view'));
        $viewFactory->shouldReceive('exists')
            ->with('dtp-preview')
            ->andReturn(false);
        $this->app->instance('view', $viewFactory);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(200);

        $caps = $response->json('data.capabilities');
        // Link is available (feature flag + DTP doc)
        $this->assertTrue($caps['previewLinkAvailable']);
        // But render is NOT available (Blade view missing)
        $this->assertFalse($caps['previewRenderable']);
    }

    // ─── Finding 9: Wrong site returns 404 ───

    public function test_wrong_site_returns_404(): void
    {
        $issue = $this->makeIssue();
        $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$otherSite->id}/magazine-issues/{$issue->id}/dtp-rollout")
            ->assertStatus(404);
    }
}
