<?php

namespace Tests\Feature\Security;

use App\Domain\Tenancy\PublicTenantResolver;
use App\Models\Asset;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F22 (audit 2026-09-22) — public routes (media, fonts, magazine viewer,
 * analytics beacon, public-site middleware) resolve the tenant of the
 * requested site/host instead of "the first tenant"; a negative lookup
 * leaves no context behind; the mapping is cached (no per-request scan).
 */
class PublicTenantResolutionTest extends TestCase
{
    private Tenant $tenant2;
    private User $owner2;
    private Site $site2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        // Tenant 1 exists (base TestCase) and is created FIRST — the old
        // LIMIT 1 lookups would only ever see it. Everything below lives in tenant 2.
        $this->tenant2 = Tenant::factory()->create();
        $this->owner2 = User::factory()->owner()->create(['tenant_id' => $this->tenant2->id]);
        $this->setTenantScope($this->owner2);
        $this->site2 = Site::factory()->create(['tenant_id' => $this->tenant2->id, 'custom_domain' => 'second.test']);
    }

    private function current(): string
    {
        return PublicTenantResolver::current(); // '' when no tenant (nil uuid) is set
    }

    public function test_media_and_fonts_resolve_the_second_tenant(): void
    {
        Storage::disk('assets')->put("sites/{$this->site2->id}/assets/pic.png", 'PNGDATA');
        $asset = Asset::create([
            'site_id' => $this->site2->id, 'original_name' => 'pic.png', 'storage_path' => "sites/{$this->site2->id}/assets/pic.png",
            'mime_type' => 'image/png', 'file_size' => 7, 'checksum' => sha1('x'),
        ]);
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'"); // anonymous request

        $this->get("/media/{$this->site2->id}/{$asset->id}")->assertOk()->assertHeader('Content-Type', 'image/png');

        // wrong site for the asset → 404, and no tenant context left behind
        $this->get("/media/{$this->tenant->id}/{$asset->id}")->assertNotFound();
        $this->assertSame('', $this->current());
    }

    public function test_magazine_issue_resolves_by_host_in_the_second_tenant(): void
    {
        Page::factory()->create(['site_id' => $this->site2->id, 'slug' => 'issue-2', 'status' => 'published', 'editor_mode' => 'magazine']);
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");
        $this->assertSame($this->site2->id, app(PublicTenantResolver::class)->siteByHost('second.test')?->id, 'resolver must find the host in tenant 2');
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");
        $this->get('http://second.test/issue/issue-2')->assertOk();
        $this->get('http://nobody.test/issue/issue-2')->assertNotFound();
        $this->assertSame('', $this->current());
    }

    public function test_beacon_and_public_site_middleware_find_the_second_tenant(): void
    {
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");
        $this->postJson("/api/v1/sites/{$this->site2->id}/t", ['p' => '/x'])->assertOk();
        $this->assertSame(1, DB::table('page_views')->where('site_id', $this->site2->id)->count());

        $this->getJson("/api/v1/sites/{$this->site2->id}/search?q=hello")->assertOk();
        $this->getJson('/api/v1/sites/00000000-0000-7000-8000-000000000000/search?q=hello')->assertNotFound();
        $this->assertSame('', $this->current());
    }

    public function test_mapping_is_cached_and_context_is_restored_by_with_tenant(): void
    {
        $resolver = app(PublicTenantResolver::class);
        DB::unprepared("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");
        $this->assertSame($this->tenant2->id, $resolver->tenantForSite($this->site2->id));

        // second lookup must not scan tenants again
        DB::enableQueryLog();
        $resolver->tenantForSite($this->site2->id);
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'tenants'))->count();
        DB::disableQueryLog();
        $this->assertSame(0, $queries, 'site→tenant mapping must be cached');

        // a negative lookup never leaves the last probed tenant set
        $this->assertNull($resolver->siteById('00000000-0000-7000-8000-000000000000'));
        $this->assertSame('', $this->current());

        // withTenant restores the previous context, even on exceptions
        PublicTenantResolver::set($this->tenant->id);
        try {
            PublicTenantResolver::withTenant($this->tenant2->id, function () {
                $this->assertSame($this->tenant2->id, $this->current());
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($this->tenant->id, $this->current());
    }
}
