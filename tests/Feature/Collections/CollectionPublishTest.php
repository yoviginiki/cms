<?php

namespace Tests\Feature\Collections;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\RecordService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\ContentCollection;
use App\Models\Page;
use App\Models\Record;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Track G2 — Tier-1 static publishing: record detail pages, paginated
 * archives, the sharded JSON search index, record templates, search island
 * shells, and the delta (stale-record) path.
 */
class CollectionPublishTest extends TestCase
{
    use BuildsCollections;

    private Site $site;
    private ContentCollection $authors;
    private ContentCollection $books;
    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->authors = $this->createAuthorsCollection($this->site);
        $this->books = $this->createBooksCollection($this->site, $this->authors);
        $this->staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($this->staging);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-builds'));
        parent::tearDown();
    }

    private function seedBooks(int $count, array $overrides = []): void
    {
        $service = app(RecordService::class);
        $asimov = $service->save($this->authors, $this->site, null, ['data' => ['name' => 'Isaac Asimov'], 'status' => 'published']);
        $genres = ['Sci-Fi', 'Fantasy', 'Mystery'];
        for ($i = 1; $i <= $count; $i++) {
            $service->save($this->books, $this->site, null, [
                'status' => 'published',
                'data' => array_merge([
                    'title' => "Book {$i}",
                    'isbn' => sprintf('PUB-%04d', $i),
                    'price' => 5 + $i,
                    'genre' => $genres[$i % 3],
                    'in_stock' => $i % 2 === 0,
                    'summary' => "<p>Summary of book {$i} about robots</p>",
                ], $overrides),
                'relations' => ['author' => [['id' => $asimov->id]]],
            ]);
        }
    }

    private function publisher(): CollectionPublishService
    {
        return app(CollectionPublishService::class);
    }

    public function test_full_build_emits_record_pages_archive_and_index(): void
    {
        $this->seedBooks(5);

        $warnings = $this->publisher()->buildAll($this->site, $this->staging);
        $this->assertSame([], $warnings);

        // Record detail page (fallback view)
        $detail = "{$this->staging}/books/book-1/index.html";
        $this->assertFileExists($detail);
        $html = File::get($detail);
        $this->assertStringContainsString('Book 1', $html);
        $this->assertStringContainsString('Isaac Asimov', $html);         // relation rendered
        $this->assertStringContainsString('Summary of book 1', $html);    // rich text
        $this->assertStringContainsString('<title>Book 1 |', $html);

        // Archive page 1
        $archive = File::get("{$this->staging}/books/index.html");
        $this->assertStringContainsString('Book 3', $archive);
        $this->assertStringContainsString('/books/book-3/', $archive);

        // Index manifest + shard
        $manifest = json_decode(File::get("{$this->staging}/books/index.json"), true);
        $this->assertSame(5, $manifest['count']);
        $this->assertSame('books', $manifest['collection']);
        $this->assertCount(1, $manifest['shards']);

        $shardPath = $this->staging . str_replace('/books/', '/books/', $manifest['shards'][0]);
        $shard = json_decode(File::get($shardPath), true);
        $this->assertCount(5, $shard);
        $row = collect($shard)->firstWhere('t', 'Book 1');
        $this->assertSame('/books/book-1/', $row['u']);
        $this->assertStringContainsString('robots', $row['s']);           // searchable rich text, lowercased
        $this->assertStringContainsString('pub-0001', $row['s']);         // searchable SKU
        $this->assertStringContainsString('isaac asimov', $row['s']);     // searchable relation → related titles
        $this->assertSame($row['f']['genre'], $shard !== [] ? $row['f']['genre'] : null);
        $this->assertContains('Isaac Asimov', $row['f']['author']);       // relation facet
    }

    public function test_archive_paginates_statically(): void
    {
        $this->books->update(['settings' => ['per_page' => 6]]);
        $this->books->refresh();
        $this->seedBooks(15);

        $this->publisher()->buildAll($this->site, $this->staging);

        $this->assertFileExists("{$this->staging}/books/index.html");
        $this->assertFileExists("{$this->staging}/books/page/2/index.html");
        $this->assertFileExists("{$this->staging}/books/page/3/index.html");
        $this->assertFileDoesNotExist("{$this->staging}/books/page/4/index.html");

        $page2 = File::get("{$this->staging}/books/page/2/index.html");
        $this->assertStringContainsString('/books/page/3/', $page2);      // next link
        $this->assertStringContainsString('aria-current="page"', $page2);
    }

    public function test_record_single_template_is_used_when_present(): void
    {
        $this->seedBooks(1);

        $template = ThemeTemplate::create([
            'site_id' => $this->site->id,
            'name' => 'Book page',
            'slug' => 'book-page',
            'type' => 'record-single',
            'collection_id' => $this->books->id,
            'is_default' => true,
        ]);
        app(BlockService::class)->syncBlocks($template, [
            ['type' => 'record-title', 'order' => 0, 'data' => ['tag' => 'h1']],
            ['type' => 'field-value', 'order' => 1, 'data' => ['field' => 'price', 'showLabel' => true]],
        ]);

        $record = Record::where('collection_id', $this->books->id)->firstOrFail();
        $this->publisher()->buildRecordPage($this->site, $this->books, $record, $this->staging);

        $html = File::get("{$this->staging}/books/{$record->slug}/index.html");
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Book 1', $html);
        $this->assertStringContainsString('6.00', $html);                  // price via field-value
        $this->assertStringNotContainsString('record-single', $html);      // fallback view NOT used
    }

    public function test_index_shards_when_over_size_limit(): void
    {
        config(['collections.shard_raw_bytes' => 600]);
        $this->seedBooks(10);

        $this->publisher()->buildAll($this->site, $this->staging);

        $manifest = json_decode(File::get("{$this->staging}/books/index.json"), true);
        $this->assertGreaterThan(1, count($manifest['shards']));
        $this->assertSame(10, $manifest['count']);

        // Every shard resolves and row counts add up
        $total = 0;
        foreach ($manifest['shards'] as $url) {
            $path = $this->staging . $url;
            $this->assertFileExists($path);
            $total += count(json_decode(File::get($path), true));
        }
        $this->assertSame(10, $total);
    }

    public function test_search_island_blocks_render_shell_and_inject_runtime(): void
    {
        $this->seedBooks(2);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'title' => 'Find books']);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'search-box', 'order' => 0, 'data' => ['collectionId' => $this->books->id]],
            ['type' => 'facet-filter', 'order' => 1, 'data' => ['collectionId' => $this->books->id]],
            ['type' => 'results-grid', 'order' => 2, 'data' => ['collectionId' => $this->books->id, 'cardFields' => ['price', 'genre']]],
        ]);

        \App\Domain\Publishing\Services\AssetPublisher::setDeployTarget($this->staging);
        try {
            $html = app(BuildPageService::class)->build($page, null, $this->site);
        } finally {
            \App\Domain\Publishing\Services\AssetPublisher::reset();
        }

        // The runtime must ship INSIDE the build (atomic swap loses anything
        // written to the live docroot mid-build).
        $this->assertNotEmpty(glob("{$this->staging}/assets/collections-search.*.js"));

        $this->assertStringContainsString('data-cs-role="search-box"', $html);
        $this->assertStringContainsString('data-cs-source="/books/index.json"', $html);
        $this->assertStringContainsString('data-cs-facet="genre"', $html);
        $this->assertStringContainsString('data-cs-card', $html);          // client card template
        $this->assertStringContainsString('collections-search.', $html);   // hashed runtime script
        $this->assertStringContainsString('Sci-Fi', $html);                // static facet options pre-JS
    }

    public function test_path_collision_with_page_skips_collection_with_warning(): void
    {
        $this->seedBooks(1);
        File::ensureDirectoryExists("{$this->staging}/books");
        File::put("{$this->staging}/books/index.html", '<html>a page named books</html>');

        $warnings = $this->publisher()->buildAll($this->site, $this->staging);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('already used', $warnings[0]);
        $this->assertStringContainsString('a page named books', File::get("{$this->staging}/books/index.html"));
        $this->assertFileDoesNotExist("{$this->staging}/books/book-1/index.html");
    }

    public function test_sitemap_includes_record_urls(): void
    {
        $this->seedBooks(2);

        $xml = app(\App\Domain\Publishing\Services\SitemapGenerator::class)->generate($this->site);

        $this->assertStringContainsString('/books/</loc>', $xml);
        $this->assertStringContainsString('/books/book-1/</loc>', $xml);
    }

    public function test_record_save_flags_delta_and_stale_endpoint_lists_it(): void
    {
        $this->seedBooks(1);
        $record = Record::where('collection_id', $this->books->id)->firstOrFail();
        $this->assertTrue($record->needs_republish);

        $response = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/stale");
        $response->assertOk();
        $this->assertNotEmpty($response->json('data.records'));
    }

    public function test_delta_republish_builds_record_and_collection_index(): void
    {
        $this->seedBooks(3);

        // Change one price → delta with just that record
        $record = Record::where('collection_id', $this->books->id)->where('title', 'Book 2')->firstOrFail();
        $data = $record->data;
        $data['price'] = 99.5;
        app(RecordService::class)->save($this->books, $this->site, $record, ['data' => $data]);

        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/stale/republish", [
            'record_ids' => [$record->id],
        ]);
        $response->assertStatus(201);
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $deployment = \App\Models\Deployment::findOrFail($response->json('data.id'));
        $deployment->refresh();
        $this->assertSame('staged', $deployment->status);
        $built = collect($deployment->metadata['built']);
        $this->assertTrue($built->contains(fn ($b) => $b['type'] === 'record' && $b['id'] === $record->id));

        $staging = $deployment->artifact_path;
        $this->assertFileExists("{$staging}/books/book-2/index.html");
        $this->assertStringContainsString('99.50', File::get("{$staging}/books/book-2/index.html"));

        // Index regenerated with the new price
        $manifest = json_decode(File::get("{$staging}/books/index.json"), true);
        $shard = json_decode(File::get($staging . $manifest['shards'][0]), true);
        $row = collect($shard)->firstWhere('t', 'Book 2');
        $this->assertSame(99.5, $row['d']['price']);
    }
}
