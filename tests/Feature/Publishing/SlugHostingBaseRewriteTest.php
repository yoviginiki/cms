<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Site;
use Tests\TestCase;

/**
 * Slug-hosted sites (no custom domain) deploy into ensodo.eu/{slug}/ — every
 * root-absolute URL in the built HTML must be prefixed with the slug or it
 * escapes the site directory.
 */
class SlugHostingBaseRewriteTest extends TestCase
{
    private function site(?string $domain): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'my-site',
            'custom_domain' => $domain,
        ]);
    }

    public function test_prefixes_root_absolute_urls_for_slug_hosted_site(): void
    {
        $html = '<a href="/za-kontakt/">c</a>'
            . '<a href="/">home</a>'
            . '<img src="/assets/files/abc.jpg" srcset="/assets/files/abc_400.webp 400w, /assets/files/abc_800.webp 800w">'
            . '<meta content="/assets/files/og.jpg">'
            . '<div style="background:url(/assets/files/bg.jpg)"></div>';

        $out = BuildPageService::rewriteBaseForSlugHosting($html, $this->site(null));

        $this->assertStringContainsString('href="/my-site/za-kontakt/"', $out);
        $this->assertStringContainsString('href="/my-site/"', $out);
        $this->assertStringContainsString('src="/my-site/assets/files/abc.jpg"', $out);
        $this->assertStringContainsString('srcset="/my-site/assets/files/abc_400.webp 400w, /my-site/assets/files/abc_800.webp 800w"', $out);
        $this->assertStringContainsString('content="/my-site/assets/files/og.jpg"', $out);
        $this->assertStringContainsString('url(/my-site/assets/files/bg.jpg)', $out);
    }

    public function test_leaves_api_protocol_relative_and_absolute_urls_alone(): void
    {
        $html = '<form action="/api/v1/sites/x/forms/submit"></form>'
            . '<script src="//cdn.example.com/x.js"></script>'
            . '<a href="https://example.com/page">x</a>'
            . '<a href="/my-site/already/">x</a>';

        $out = BuildPageService::rewriteBaseForSlugHosting($html, $this->site(null));

        $this->assertStringContainsString('action="/api/v1/sites/x/forms/submit"', $out);
        $this->assertStringContainsString('src="//cdn.example.com/x.js"', $out);
        $this->assertStringContainsString('href="https://example.com/page"', $out);
        $this->assertStringNotContainsString('/my-site/my-site/', $out);
    }

    public function test_custom_domain_site_is_untouched(): void
    {
        $html = '<a href="/za-kontakt/">c</a><img src="/assets/files/abc.jpg">';

        $out = BuildPageService::rewriteBaseForSlugHosting($html, $this->site('example.org'));

        $this->assertSame($html, $out);
    }
}
