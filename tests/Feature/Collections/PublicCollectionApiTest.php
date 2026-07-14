<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Track G3 — the read-only public collections API: dynamic-tier gate,
 * tsvector search incl. SKU + pivot supplier part numbers, facet filters +
 * counts, cursor pagination with hard caps, version-keyed cache
 * invalidation, unauthenticated site resolution, cross-site isolation,
 * rate limiting, and the no-public-writes route-list assertion.
 */
class PublicCollectionApiTest extends TestCase
{
    use BuildsCollections;

    private Site $site;
    private ContentCollection $authors;
    private ContentCollection $books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->authors = $this->createAuthorsCollection($this->site);
        $this->books = $this->createBooksCollection($this->site, $this->authors);
    }

    private function makeDynamic(ContentCollection $collection): void
    {
        $collection->update(['tier' => 'dynamic']);
        $collection->refresh();
    }

    private function seedBooks(int $count = 5): void
    {
        $rs = app(RecordService::class);
        $asimov = $rs->save($this->authors, $this->site, null, ['status' => 'published', 'data' => ['name' => 'Isaac Asimov']]);
        $leguin = $rs->save($this->authors, $this->site, null, ['status' => 'published', 'data' => ['name' => 'Ursula K. Le Guin']]);
        $genres = ['Sci-Fi', 'Fantasy', 'Mystery'];
        for ($i = 1; $i <= $count; $i++) {
            $rs->save($this->books, $this->site, null, [
                'status' => 'published',
                'data' => [
                    'title' => "Book {$i}", 'isbn' => sprintf('API-%04d', $i), 'price' => 5 + $i,
                    'genre' => $genres[$i % 3], 'in_stock' => $i % 2 === 0,
                    'summary' => "<p>Story {$i} about starships</p>",
                ],
                'relations' => ['author' => [['id' => $i % 2 === 0 ? $asimov->id : $leguin->id]]],
            ]);
        }
    }

    private function base(): string
    {
        return "/api/v1/public/{$this->site->id}/collections/books/records";
    }

    public function test_api_serves_dynamic_collections_only(): void
    {
        $this->seedBooks(2);

        // static tier → 404 (the wall is explicit)
        $this->getJson($this->base())->assertStatus(404);

        $this->makeDynamic($this->books);
        $this->getJson($this->base())->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_unauthenticated_site_resolution_and_cross_site_isolation(): void
    {
        $this->seedBooks(1);
        $this->makeDynamic($this->books);

        // No actingAs anywhere — SetTenantFromPublicSite must resolve the tenant.
        $this->getJson($this->base())->assertOk();

        // Another site of the same tenant can't reach this collection.
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->getJson("/api/v1/public/{$other->id}/collections/books/records")->assertStatus(404);

        // Nonexistent site → 404, malformed id → 404.
        $this->getJson('/api/v1/public/00000000-0000-0000-0000-000000000009/collections/books/records')->assertStatus(404);
        $this->getJson('/api/v1/public/not-a-uuid/collections/books/records')->assertStatus(404);
    }

    public function test_full_text_search_matches_sku_and_pivot_supplier_part_numbers(): void
    {
        [$suppliers, $parts] = $this->createPartsAndSuppliers($this->site);
        $parts->update(['tier' => 'dynamic']);
        $rs = app(RecordService::class);
        $acme = $rs->save($suppliers, $this->site, null, ['status' => 'published', 'data' => ['name' => 'Acme', 'lead_time' => 5]]);
        $rs->save($parts, $this->site, null, [
            'status' => 'published',
            'data' => ['name' => 'Compressor', 'part_number' => 'CMP-100'],
            'relations' => ['suppliers' => [['id' => $acme->id, 'pivot' => ['supplier_part_number' => 'ACME-777', 'supplier_price' => 19.9]]]],
        ]);
        $rs->save($parts, $this->site, null, [
            'status' => 'published',
            'data' => ['name' => 'Fan blade', 'part_number' => 'FAN-200'],
        ]);

        $base = "/api/v1/public/{$this->site->id}/collections/parts/records";

        // Own part number (prefix match — type-ahead)
        $this->getJson("{$base}?q=cmp-1")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.t', 'Compressor');
        // Supplier's part number from pivot data
        $this->getJson("{$base}?q=acme-777")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.t', 'Compressor');
        // Cross-search: name term
        $this->getJson("{$base}?q=fan")->assertOk()->assertJsonPath('data.0.t', 'Fan blade');
        // No match
        $this->getJson("{$base}?q=zzznope")->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_facet_filters_and_counts(): void
    {
        $this->seedBooks(6);
        $this->makeDynamic($this->books);

        $response = $this->getJson($this->base() . '?genre=Sci-Fi')->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertSame('Sci-Fi', $row['f']['genre']);
        }

        // boolean facet
        $inStock = $this->getJson($this->base() . '?in_stock=true')->assertOk();
        $this->assertSame(3, $inStock->json('meta.total'));

        // relation facet by related title
        $byAuthor = $this->getJson($this->base() . '?author=' . urlencode('Isaac Asimov'))->assertOk();
        $this->assertSame(3, $byAuthor->json('meta.total'));

        // counts present for every facetable field
        $facets = $response->json('meta.facets');
        $this->assertArrayHasKey('genre', $facets);
        $this->assertArrayHasKey('in_stock', $facets);
        $this->assertArrayHasKey('author', $facets);
        // counts for genre computed EXCLUDING the genre filter itself
        $this->assertSame(6, array_sum($facets['genre']));
    }

    public function test_cursor_pagination_and_per_page_cap(): void
    {
        $this->seedBooks(7);
        $this->makeDynamic($this->books);

        $capped = $this->getJson($this->base() . '?per_page=500')->assertOk();
        $this->assertSame(50, $capped->json('meta.per_page'));

        $page1 = $this->getJson($this->base() . '?per_page=3&sort=title&direction=asc')->assertOk();
        $this->assertCount(3, $page1->json('data'));
        $cursor = $page1->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $page2 = $this->getJson($this->base() . '?per_page=3&sort=title&direction=asc&cursor=' . urlencode($cursor))->assertOk();
        $this->assertCount(3, $page2->json('data'));
        $this->assertNotSame($page1->json('data.0.t'), $page2->json('data.0.t'));
    }

    public function test_cache_invalidates_when_a_record_changes(): void
    {
        $this->seedBooks(2);
        $this->makeDynamic($this->books);

        $this->getJson($this->base())->assertOk()->assertJsonPath('meta.total', 2);

        app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'published',
            'data' => ['title' => 'Fresh Book', 'isbn' => 'API-9999'],
        ]);

        // Version key bumped → new cache entry, fresh data immediately.
        $this->getJson($this->base())->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_record_detail_endpoint_serves_published_only(): void
    {
        $this->seedBooks(1);
        $this->makeDynamic($this->books);

        $this->getJson($this->base() . '/book-1')
            ->assertOk()
            ->assertJsonPath('data.title', 'Book 1')
            ->assertJsonPath('data.url', '/books/book-1/');

        $draft = app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'draft', 'data' => ['title' => 'Hidden', 'isbn' => 'API-1111'],
        ]);
        $this->getJson($this->base() . "/{$draft->slug}")->assertStatus(404);
    }

    public function test_no_write_routes_exist_in_public_namespace(): void
    {
        $offenders = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/public/')) {
                $methods = array_diff($route->methods(), ['GET', 'HEAD']);
                if ($methods !== []) {
                    $offenders[] = $route->uri() . ' [' . implode(',', $methods) . ']';
                }
            }
        }
        $this->assertSame([], $offenders, 'Public collections namespace must be read-only.');
    }

    public function test_rate_limit_enforced_per_ip(): void
    {
        $this->seedBooks(1);
        $this->makeDynamic($this->books);

        $status = 200;
        for ($i = 0; $i < 61 && $status !== 429; $i++) {
            $status = $this->getJson($this->base())->getStatusCode();
        }

        $this->assertSame(429, $status);
    }

    public function test_cors_header_only_for_site_domains(): void
    {
        $this->seedBooks(1);
        $this->makeDynamic($this->books);

        $allowed = $this->getJson($this->base(), ['Origin' => "https://{$this->site->slug}.ensodo.eu"]);
        $this->assertSame("https://{$this->site->slug}.ensodo.eu", $allowed->headers->get('Access-Control-Allow-Origin'));

        $denied = $this->getJson($this->base(), ['Origin' => 'https://evil.example.com']);
        $this->assertNull($denied->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_tier_flip_changes_publish_artifacts_both_ways(): void
    {
        $this->seedBooks(2);
        $staging = storage_path('app/test-builds/' . uniqid());
        \Illuminate\Support\Facades\File::ensureDirectoryExists($staging);
        $publisher = app(\App\Domain\Collections\Services\CollectionPublishService::class);

        // Static: pages + archive + index; islands in static mode
        $publisher->buildAll($this->site, $staging);
        $this->assertFileExists("{$staging}/books/book-1/index.html");
        $this->assertFileExists("{$staging}/books/index.json");

        // Flip to dynamic: republish → pages (SEO default ON) + archive, NO index; islands in api mode
        $this->makeDynamic($this->books);
        $staging2 = storage_path('app/test-builds/' . uniqid());
        \Illuminate\Support\Facades\File::ensureDirectoryExists($staging2);
        $publisher->buildAll($this->site, $staging2);
        $this->assertFileExists("{$staging2}/books/book-1/index.html");
        $this->assertFileExists("{$staging2}/books/index.html");
        $this->assertFileDoesNotExist("{$staging2}/books/index.json");

        // Search blocks resolve to the API source now
        [$mode, $url] = \App\Support\Blocks\RecordDisplay::searchSource($this->books, $this->site);
        $this->assertSame('api', $mode);
        $this->assertStringContainsString("/api/v1/public/{$this->site->id}/collections/books/records", $url);

        // And back to static: index returns
        $this->books->update(['tier' => 'static']);
        $this->books->refresh();
        $staging3 = storage_path('app/test-builds/' . uniqid());
        \Illuminate\Support\Facades\File::ensureDirectoryExists($staging3);
        $publisher->buildAll($this->site, $staging3);
        $this->assertFileExists("{$staging3}/books/index.json");

        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/test-builds'));
    }
}
