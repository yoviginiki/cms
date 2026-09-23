<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\CloudflarePurger;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflarePurgeTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->build = storage_path('framework/testing/cf-purge-build');
        File::deleteDirectory($this->build);
        File::ensureDirectoryExists("{$this->build}/about");
        File::put("{$this->build}/index.html", 'x');
        File::put("{$this->build}/about/index.html", 'x');
        File::put("{$this->build}/sitemap.xml", 'x');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->build);
        parent::tearDown();
    }

    public function test_noop_without_credentials(): void
    {
        config(['cms.cloudflare.api_token' => '', 'cms.cloudflare.zone_id' => '']);
        Http::fake();

        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertSame(0, CloudflarePurger::purgeSite($site, $this->build));
        Http::assertNothingSent();
    }

    public function test_purges_the_zone_when_configured(): void
    {
        // Contract since 2026-08 (see CloudflarePurger doc): ONE purge_everything
        // call — per-URL purge never reached the cached, content-hashed assets.
        config(['cms.cloudflare.api_token' => 'test-token', 'cms.cloudflare.zone_id' => 'zone123']);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertSame(1, CloudflarePurger::purgeSite($site, $this->build));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/zones/zone123/purge_cache')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ($request['purge_everything'] ?? null) === true);
    }

    public function test_failed_batches_do_not_throw(): void
    {
        config(['cms.cloudflare.api_token' => 'test-token', 'cms.cloudflare.zone_id' => 'zone123']);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'bad token']]], 403),
        ]);

        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertSame(0, CloudflarePurger::purgeSite($site, $this->build));
    }
}
