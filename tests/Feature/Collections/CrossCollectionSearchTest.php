<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Collections v3 — cross-collection search: the site-level /search/index.json
 * manifest and the '*' search wizard page.
 */
class CrossCollectionSearchTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);

        $service = app(CollectionService::class);
        $records = app(RecordService::class);
        foreach (['Cats', 'Dogs'] as $name) {
            $collection = $service->create($this->site, [
                'name' => $name,
                'tier' => 'static',
                'schema' => [
                    'fields' => [['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'searchable' => true]],
                    'title_field' => 'title',
                ],
            ]);
            $records->save($collection, $this->site, null, [
                'data' => ['title' => "{$name} one"], 'status' => 'published',
            ]);
        }
    }

    public function test_site_manifest_lists_static_collection_indexes(): void
    {
        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            app(CollectionPublishService::class)->buildAll($this->site, $staging);

            $base = \App\Support\Blocks\RecordDisplay::sitePathBase($this->site);
            $manifest = json_decode(File::get("{$staging}/search/index.json"), true);
            $this->assertCount(2, $manifest['sources']);
            $this->assertEqualsCanonicalizing(
                ["{$base}/cats/index.json", "{$base}/dogs/index.json"],
                array_column($manifest['sources'], 'manifest')
            );
            $this->assertSame('_type', $manifest['fields'][0]['key']);
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }
    }

    public function test_pages_source_indexes_published_pages(): void
    {
        \App\Models\Page::factory()->published()->create([
            'site_id' => $this->site->id,
            'title' => 'Shipping policy',
            'slug' => 'shipping',
        ]);

        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            // Simulate the page having been built before collections, with
            // site chrome outside <main> that must NOT be indexed.
            File::ensureDirectoryExists("{$staging}/shipping");
            File::put("{$staging}/shipping/index.html",
                '<html><body><nav>CHROME-WORD</nav><main><h1>Shipping policy</h1><p>Orders ship worldwide via courier.</p></main></body></html>');

            app(CollectionPublishService::class)->buildAll($this->site, $staging);

            $base = \App\Support\Blocks\RecordDisplay::sitePathBase($this->site);
            $manifest = json_decode(File::get("{$staging}/search/index.json"), true);
            $this->assertContains("{$base}/search/pages.json", array_column($manifest['sources'], 'manifest'));

            $pagesManifest = json_decode(File::get("{$staging}/search/pages.json"), true);
            $shardPath = substr($pagesManifest['shards'][0], strlen($base));
            $rows = json_decode(File::get($staging . $shardPath), true);
            $row = collect($rows)->firstWhere('u', "{$base}/shipping/");
            $this->assertNotNull($row);
            $this->assertSame('Shipping policy', $row['t']);
            $this->assertStringContainsString('worldwide via courier', $row['s']);
            $this->assertStringNotContainsString('chrome-word', $row['s']);
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }
    }

    public function test_wizard_builds_cross_search_page(): void
    {
        $res = $this->actingAs($this->owner)->postJson("/api/v1/sites/{$this->site->id}/wizard/search", [
            'collection_id' => '*',
            'page_title' => 'Find anything',
            'build_page' => true,
        ]);
        $res->assertCreated();
        $this->assertNotNull($res->json('data.page.id'));

        $page = \App\Models\Page::findOrFail($res->json('data.page.id'));
        $blocks = \App\Models\Block::where('blockable_id', $page->id)->get();
        $starred = $blocks->filter(fn ($b) => ($b->data['collectionId'] ?? null) === '*');
        $this->assertGreaterThanOrEqual(3, $starred->count());
    }
}
