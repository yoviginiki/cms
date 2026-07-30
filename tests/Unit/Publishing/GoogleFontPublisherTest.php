<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\GoogleFontPublisher;
use Tests\TestCase;

/**
 * Self-hosting is a publish-time optimization; outside a build (admin preview,
 * no deploy target) it must degrade gracefully to the @import so text always
 * loads. This guards that fallback contract without hitting the network.
 */
class GoogleFontPublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        AssetPublisher::setDeployTarget(null);
        parent::tearDown();
    }

    public function test_returns_null_without_a_deploy_target(): void
    {
        AssetPublisher::setDeployTarget(null);

        $this->assertNull((new GoogleFontPublisher())->localCss(['Inter']));
    }

    public function test_returns_null_for_no_families(): void
    {
        AssetPublisher::setDeployTarget(sys_get_temp_dir());

        $this->assertNull((new GoogleFontPublisher())->localCss([]));
        $this->assertNull((new GoogleFontPublisher())->localCss(['', '  ']));
    }
}
