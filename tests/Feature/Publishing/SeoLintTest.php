<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\InternalLinkChecker;
use App\Domain\Publishing\Services\OutputValidator;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Track F5 — publish-time SEO lint: thin content, featured image, JSON-LD
 * validity, and the cross-page internal link checker. Warnings, never blocking.
 */
class SeoLintTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function validate(string $html, $content): array
    {
        return app(OutputValidator::class)->validate($html, $content, $this->site);
    }

    private function htmlPage(string $main, string $head = ''): string
    {
        return '<!DOCTYPE html><html lang="en"><head><title>T</title>'
            . '<meta name="description" content="A perfectly reasonable description of this page.">'
            . '<link rel="canonical" href="https://x.test/">' . $head
            . '</head><body><main><h1>Title</h1>' . $main . '</main></body></html>';
    }

    public function test_thin_content_is_flagged_as_warning_only(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $result = $this->validate($this->htmlPage('<p>Just a few words here.</p>'), $page);

        $this->assertTrue($result['passed']);
        $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'Thin content')));
    }

    public function test_substantial_content_is_not_flagged_thin(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $body = '<p>' . implode(' ', array_fill(0, 200, 'word')) . '</p>';
        $result = $this->validate($this->htmlPage($body), $page);

        $this->assertEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'Thin content')));
    }

    public function test_post_without_featured_image_is_flagged(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => null, 'featured_image' => null]);
        $result = $this->validate($this->htmlPage('<p>Body</p>'), $post);

        $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'featured image')));

        $withImage = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => null, 'featured_image' => '/img.jpg']);
        $result2 = $this->validate($this->htmlPage('<p>Body</p>'), $withImage);
        $this->assertEmpty(array_filter($result2['warnings'], fn ($w) => str_contains($w, 'featured image')));
    }

    public function test_invalid_json_ld_is_flagged(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $head = '<script type="application/ld+json">{not json}</script>'
            . '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"name":"no type"}]}</script>';
        $result = $this->validate($this->htmlPage('<p>Body</p>', $head), $page);

        $this->assertTrue($result['passed']); // warnings never block
        $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'not valid JSON')));
        $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'without @type')));
    }

    public function test_valid_json_ld_passes_clean(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $head = '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage","name":"T"}]}</script>';
        $result = $this->validate($this->htmlPage('<p>Body</p>', $head), $page);

        $this->assertEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'JSON-LD')));
    }

    public function test_internal_link_checker_reports_broken_links_only(): void
    {
        $staging = storage_path('framework/testing/lint-' . uniqid());
        File::ensureDirectoryExists("{$staging}/about");
        File::put("{$staging}/about/index.html", '<html></html>');
        File::put("{$staging}/exists.pdf", 'x');
        File::put(
            "{$staging}/index.html",
            '<a href="/about/">ok</a> <a href="/missing/">broken</a> <a href="/exists.pdf">ok</a>'
            . ' <a href="/gone.pdf">broken file</a> <a href="/admin/x">ignored</a> <a href="https://ext.example/">ext</a>'
        );

        $warnings = app(InternalLinkChecker::class)->check($staging);
        File::deleteDirectory($staging);

        $this->assertCount(2, $warnings);
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, '/missing/')));
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, '/gone.pdf')));
    }

    public function test_internal_link_checker_strips_slug_base_prefix(): void
    {
        // Slug-hosted sites emit "/{slug}/…" links; the build tree has no such
        // directory, so the checker must resolve them with the prefix stripped.
        $staging = storage_path('framework/testing/lint-' . uniqid());
        File::ensureDirectoryExists("{$staging}/about");
        File::put("{$staging}/about/index.html", '<html></html>');
        File::put(
            "{$staging}/index.html",
            '<a href="/my-site/about/">ok</a> <a href="/my-site/">home ok</a> <a href="/my-site/missing/">broken</a>'
        );

        $warnings = app(InternalLinkChecker::class)->check($staging, 50, '/my-site');
        File::deleteDirectory($staging);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('/missing/', $warnings[0]);
    }

    public function test_internal_link_checker_caps_findings(): void
    {
        $staging = storage_path('framework/testing/lint-' . uniqid());
        File::ensureDirectoryExists($staging);
        $links = '';
        for ($i = 0; $i < 60; $i++) {
            $links .= "<a href=\"/missing-{$i}/\">x</a> ";
        }
        File::put("{$staging}/index.html", $links);

        $warnings = app(InternalLinkChecker::class)->check($staging, 50);
        File::deleteDirectory($staging);

        $this->assertCount(51, $warnings); // 50 findings + truncation notice
        $this->assertStringContainsString('truncated', end($warnings));
    }
}
