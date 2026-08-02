<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\ProjectionPublisher;
use App\Models\Site;
use Tests\TestCase;

/**
 * Pure-ish unit coverage for the integration helpers that do not require the
 * database (path derivation and the enablement gate). The full publish flow is
 * exercised by the Phase 6 publish/Playwright scenarios.
 */
class ProjectionPublisherTest extends TestCase
{
    private function publisher(): ProjectionPublisher
    {
        return app(ProjectionPublisher::class);
    }

    public function test_url_derivation(): void
    {
        $p = $this->publisher();
        $this->assertSame('/', $p->urlForPath('index.html'));
        $this->assertSame('/about/', $p->urlForPath('about/index.html'));
        $this->assertSame('/blog/hello/', $p->urlForPath('blog/hello/index.html'));
    }

    public function test_sidecar_path_derivation(): void
    {
        $p = $this->publisher();
        $this->assertSame('index.json', $p->sidecarPath('index.html'));
        $this->assertSame('about/index.json', $p->sidecarPath('about/index.html'));
        $this->assertSame('feed.xml.json', $p->sidecarPath('feed.xml'));
    }

    public function test_disabled_by_default(): void
    {
        $site = new Site(['settings' => []]);
        $this->assertFalse($this->publisher()->isEnabled($site));

        $site2 = new Site(['settings' => ['crawler_policy' => ['projection_access' => 'none']]]);
        $this->assertFalse($this->publisher()->isEnabled($site2));
    }

    public function test_enabled_only_when_projection_access_public(): void
    {
        $site = new Site(['settings' => ['crawler_policy' => ['projection_access' => 'public']]]);
        $this->assertTrue($this->publisher()->isEnabled($site));
    }
}
