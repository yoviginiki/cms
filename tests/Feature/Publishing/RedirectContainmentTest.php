<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Domain\Publishing\Support\RedirectRules;
use App\Models\Redirect;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F04 (audit 2026-09-22) — a redirect's source_path must never let the
 * redirect-stub writer leave the staging tree, and target_url must be a
 * safe destination in every output format (HTML stub, _redirects, .htaccess).
 */
class RedirectContainmentTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public static function traversalSources(): array
    {
        return [
            'parent segment' => ['../neighbour'],
            'nested parent' => ['/a/../b'],
            'leading dot dir' => ['/./x'],
            'encoded parent' => ['/%2e%2e/x'],
            'encoded slash' => ['/a%2fb/..'],
            'backslash' => ['\\x'],
            'newline' => ["/ok\n/evil"],
            'control char' => ["/ok\x01"],
            'absolute-looking double slash' => ['//evil.test/x'],
            'empty' => [''],
            'root only' => ['/'],
            'space' => ['/has space'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('traversalSources')]
    public function test_api_rejects_unsafe_source_paths(string $source): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => $source,
                'target_url' => '/new',
            ], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_path');
    }

    public static function unsafeTargets(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,x'],
            'newline' => ["/new\nSet-Cookie: x"],
            'space' => ['/new page'],
            'ftp' => ['ftp://example.com/x'],
            'empty' => [''],
            'relative without slash' => ['new-page'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeTargets')]
    public function test_api_rejects_unsafe_targets(string $target): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/old',
                'target_url' => $target,
            ], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_url');
    }

    public function test_api_accepts_normal_literal_and_regex_redirects(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/2026-04-15-produkti-katalog/',
                'target_url' => '/produkti-katalog/',
            ], $this->apiHeaders())
            ->assertStatus(201);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/old-page',
                'target_url' => 'https://example.com/landing?x=1#top',
                'status_code' => 302,
            ], $this->apiHeaders())
            ->assertStatus(201);

        // Regex sources are an explicit, separate kind.
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/about-us/?',
                'target_url' => '/en/about-us/',
                'is_regex' => true,
            ], $this->apiHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.is_regex', true);

        // ...and regex metacharacters are refused for literal redirects.
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/redirects", [
                'source_path' => '/about-us/?',
                'target_url' => '/en/about-us/',
            ], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_path');

        // Update path is guarded the same way.
        $r = Redirect::where('site_id', $this->site->id)->where('source_path', '/old-page')->firstOrFail();
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/redirects/{$r->id}", ['source_path' => '/../x'], $this->apiHeaders())
            ->assertStatus(422);
    }

    public function test_build_never_writes_a_stub_outside_staging_even_for_legacy_rows(): void
    {
        $root = storage_path('framework/testing/redir-' . uniqid());
        $staging = "{$root}/staging";
        File::ensureDirectoryExists($staging);
        File::put("{$root}/sentinel.html", 'untouched');
        File::ensureDirectoryExists("{$root}/neighbour");
        File::put("{$root}/neighbour/index.html", 'untouched');

        // Rows written before validation existed (or via CLI) — must be contained at write time.
        foreach (['../neighbour', '/../sentinel', '/a/../../neighbour', "/x\n/y", '/deep/../../../etc'] as $src) {
            Redirect::create(['site_id' => $this->site->id, 'source_path' => $src, 'target_url' => '/new', 'status_code' => 301]);
        }
        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/legit', 'target_url' => '/new', 'status_code' => 301]);

        $this->invokeManifest($staging);

        $this->assertStringEqualsFile("{$root}/sentinel.html", 'untouched');
        $this->assertStringEqualsFile("{$root}/neighbour/index.html", 'untouched');
        $this->assertFileDoesNotExist("{$root}/sentinel/index.html");
        $this->assertDirectoryDoesNotExist("{$root}/etc");
        $this->assertFileExists("{$staging}/legit/index.html");

        // The unsafe rows also never reach the line-based outputs.
        $lines = file("{$staging}/_redirects", FILE_IGNORE_NEW_LINES);
        $this->assertSame(['/legit /new 301'], $lines);
        $this->assertStringNotContainsString('..', file_get_contents("{$staging}/.htaccess"));

        File::deleteDirectory($root);
    }

    public function test_stub_does_not_overwrite_a_real_page_and_targets_are_escaped(): void
    {
        $root = storage_path('framework/testing/redir-' . uniqid());
        $staging = "{$root}/staging";
        File::ensureDirectoryExists("{$staging}/about");
        File::put("{$staging}/about/index.html", 'REAL PAGE');

        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/about', 'target_url' => '/elsewhere', 'status_code' => 301]);
        Redirect::create(['site_id' => $this->site->id, 'source_path' => '/quote', 'target_url' => '/x"><script>alert(1)</script>', 'status_code' => 301]);

        $this->invokeManifest($staging);

        $this->assertStringEqualsFile("{$staging}/about/index.html", 'REAL PAGE');
        $stub = file_get_contents("{$staging}/quote/index.html");
        $this->assertStringNotContainsString('<script>alert', $stub);
        $this->assertStringNotContainsString('/x"><script>', $stub);

        File::deleteDirectory($root);
    }

    public function test_rules_helper_is_the_single_source_of_truth(): void
    {
        $this->assertSame('/a/b', RedirectRules::normalizeLiteralSource('/a/b/'));
        $this->assertSame('/a/b', RedirectRules::normalizeLiteralSource('a/b'));
        $this->assertNull(RedirectRules::normalizeLiteralSource('/a/../b'));
        $this->assertNull(RedirectRules::normalizeLiteralSource('/a/./b'));
        $this->assertNull(RedirectRules::normalizeLiteralSource('/'));
        $this->assertSame('a/b', RedirectRules::stubRelativeDir('/a/b/?'));
        $this->assertNull(RedirectRules::stubRelativeDir('/a/(b|c)'));
        $this->assertNull(RedirectRules::stubRelativeDir('../x'));
    }

    private function invokeManifest(string $staging): void
    {
        $job = new PublishSiteJob(\App\Models\Deployment::create([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => 'building',
            'triggered_by' => $this->owner->id, 'metadata' => [],
        ]));
        $m = new \ReflectionMethod($job, 'buildRedirectsManifest');
        $m->setAccessible(true);
        $m->invoke($job, $this->site, $staging);
    }
}
