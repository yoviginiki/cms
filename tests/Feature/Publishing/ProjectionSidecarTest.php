<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use Tests\TestCase;

/**
 * Phase 4 — Content Projection Layer integration into the publish pipeline.
 * Sidecars are gated behind settings.crawler_policy.projection_access, default
 * off, so a non-opted-in site publishes byte-for-byte as before.
 */
class ProjectionSidecarTest extends TestCase
{
    private function siteWithContent(string $projectionAccess): array
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);

        $page = Page::factory()->create([
            'site_id' => $site->id,
            'status' => 'published',
        ]);
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'homepage_id' => $page->id,
            'default_locale' => 'en',
            'crawler_policy' => ['projection_access' => $projectionAccess],
        ])]);

        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'Welcome Home Heading', 'level' => 'h2'],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'rich-text', 'order' => 1,
            'data' => ['content' => '<p>Some body prose with a <a href="/next">link</a>.</p>'],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'image', 'order' => 2,
            'data' => ['url' => 'https://cdn.example/cat.jpg', 'alt' => 'A friendly cat'],
        ]);

        return [$site->fresh(), $page->fresh()];
    }

    public function test_sidecar_and_manifest_are_written_when_enabled(): void
    {
        [$site, $page] = $this->siteWithContent('public');
        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        $this->assertFileExists("{$docroot}/index.json");
        $this->assertFileExists("{$docroot}/manifest.json");

        $sidecar = json_decode(file_get_contents("{$docroot}/index.json"), true);
        $this->assertIsArray($sidecar);
        $this->assertSame('https://schema.org', $sidecar['schema_org']['@context']);
        $image = collect($sidecar['schema_org']['@graph'])->firstWhere('@type', 'ImageObject');
        $this->assertNotNull($image, 'ImageObject node present');
        $this->assertSame('A friendly cat', $image['name']);

        $manifest = json_decode(file_get_contents("{$docroot}/manifest.json"), true);
        $this->assertSame('1.0', $manifest['manifest_version']);
        $this->assertNotEmpty($manifest['pages']);
        $this->assertSame('/index.json', $manifest['pages'][0]['projection']);
        $this->assertArrayHasKey('content_hash', $manifest['pages'][0]);

        // Phase 4.5: the full internal projection is stored with the version.
        $version = PageVersion::where('page_id', $page->id)->orderByDesc('version_number')->first();
        $this->assertNotNull($version->projection_snapshot);
        $this->assertSame('1.0', $version->projection_snapshot['projection_version']);
        $this->assertSame($version->id, $version->projection_snapshot['source']['page_version_id']);
        $this->assertSame($version->projection_hash, $version->projection_snapshot['source']['content_hash']);
        $this->assertSame($manifest['pages'][0]['content_hash'], $version->projection_hash);
    }

    public function test_no_projection_files_when_disabled(): void
    {
        [$site, $page] = $this->siteWithContent('none');
        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        // Regression: HTML still ships, projection artifacts do not.
        $this->assertFileExists("{$docroot}/index.html");
        $this->assertFileDoesNotExist("{$docroot}/index.json");
        $this->assertFileDoesNotExist("{$docroot}/manifest.json");

        // And nothing is stored on the version either.
        $version = PageVersion::where('page_id', $page->id)->orderByDesc('version_number')->first();
        $this->assertNull($version->projection_snapshot);
        $this->assertNull($version->projection_hash);
    }
}
