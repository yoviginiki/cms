<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Tests\TestCase;

class RecordCrudTest extends TestCase
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

    private function author(string $name): Record
    {
        return app(RecordService::class)->save($this->authors, $this->site, null, [
            'data' => ['name' => $name], 'status' => 'published',
        ]);
    }

    public function test_creates_record_with_typed_fields_and_derived_title_slug(): void
    {
        $asimov = $this->author('Isaac Asimov');

        $response = $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records",
            [
                'status' => 'published',
                'data' => [
                    'title' => 'Foundation',
                    'isbn' => ' 978-0553293357 ',
                    'price' => '7,99',
                    'genre' => 'Sci-Fi',
                    'tags' => ['classic', 'classic', 'new'],
                    'released' => '1951-06-01',
                    'in_stock' => 'yes',
                    'publisher_url' => 'https://example.com/foundation',
                    'contact_email' => 'Sales@Example.COM',
                    'contact_phone' => '+359 2 123-456',
                    'summary' => '<p>Classic <script>alert(1)</script>space opera</p>',
                ],
                'relations' => ['author' => [['id' => $asimov->id]]],
            ],
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Foundation')
            ->assertJsonPath('data.slug', 'foundation')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.data.isbn', '978-0553293357')
            ->assertJsonPath('data.data.price', 7.99)
            ->assertJsonPath('data.data.tags', ['classic', 'new'])
            ->assertJsonPath('data.data.in_stock', true)
            ->assertJsonPath('data.data.contact_email', 'sales@example.com')
            ->assertJsonPath('data.relations.author.0.title', 'Isaac Asimov');

        $summary = $response->json('data.data.summary');
        $this->assertStringNotContainsString('<script', $summary);
        $this->assertStringContainsString('space opera', $summary);
        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_validation_errors_are_keyed_per_field(): void
    {
        $response = $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records",
            [
                'data' => [
                    // missing required title
                    'genre' => 'Horror',            // not an option
                    'publisher_url' => 'javascript:alert(1)',
                    'contact_email' => 'not-an-email',
                    'released' => 'not-a-date',
                ],
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        foreach (['data.title', 'data.genre', 'data.publisher_url', 'data.contact_email', 'data.released'] as $key) {
            $this->assertArrayHasKey($key, $errors);
        }
    }

    public function test_unique_isbn_violation_is_reported_cleanly(): void
    {
        $save = fn () => $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records",
            ['data' => ['title' => 'Foundation', 'isbn' => '978-0553293357']],
        );

        $save()->assertStatus(201);

        $response = $save();
        $response->assertStatus(422);
        $this->assertStringContainsString('already used', $response->json('errors')['data.isbn'][0]);
    }

    public function test_sku_values_are_normalized_before_unique_comparison(): void
    {
        app(RecordService::class)->save($this->books, $this->site, null, [
            'data' => ['title' => 'A', 'isbn' => 'abc 123'],
        ]);

        // Different casing/whitespace, same normalized SKU → rejected.
        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records",
            ['data' => ['title' => 'B', 'isbn' => '  ABC    123 ']],
        )->assertStatus(422);
    }

    public function test_relation_mode_one_rejects_multiple_targets(): void
    {
        [, $parts] = $this->createPartsAndSuppliers($this->site);

        // Build a mode-one relation collection on the fly.
        $shelves = app(\App\Domain\Collections\Services\CollectionService::class)->create($this->site, [
            'name' => 'Shelves',
            'schema' => [
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'required' => true],
                    ['key' => 'part', 'label' => 'Part', 'type' => 'relation', 'relation' => ['collection_id' => $parts->id, 'mode' => 'one']],
                ],
                'title_field' => 'label',
            ],
        ]);

        $p1 = app(RecordService::class)->save($parts, $this->site, null, ['data' => ['name' => 'Fan', 'part_number' => 'F-1']]);
        $p2 = app(RecordService::class)->save($parts, $this->site, null, ['data' => ['name' => 'Coil', 'part_number' => 'C-1']]);

        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$shelves->id}/records",
            ['data' => ['label' => 'A1'], 'relations' => ['part' => [['id' => $p1->id], ['id' => $p2->id]]]],
        )->assertStatus(422);
    }

    public function test_pivot_fields_are_validated_and_stored(): void
    {
        [$suppliers, $parts] = $this->createPartsAndSuppliers($this->site);

        $acme = app(RecordService::class)->save($suppliers, $this->site, null, ['data' => ['name' => 'Acme', 'lead_time' => 5]]);

        // Missing required pivot field → 422
        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$parts->id}/records",
            [
                'data' => ['name' => 'Compressor', 'part_number' => 'CMP-100'],
                'relations' => ['suppliers' => [['id' => $acme->id, 'pivot' => ['supplier_price' => 10]]]],
            ],
        )->assertStatus(422);

        // Valid pivot lands, price coerced, SKU normalized
        $response = $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$parts->id}/records",
            [
                'data' => ['name' => 'Compressor', 'part_number' => 'CMP-100'],
                'relations' => ['suppliers' => [[
                    'id' => $acme->id,
                    'pivot' => ['supplier_part_number' => 'acme-cmp 100', 'supplier_price' => '19,90'],
                ]]],
            ],
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.relations.suppliers.0.pivot.supplier_part_number', 'ACME-CMP 100')
            ->assertJsonPath('data.relations.suppliers.0.pivot.supplier_price', 19.9);
    }

    public function test_list_supports_quick_search_sort_and_pagination(): void
    {
        $service = app(RecordService::class);
        foreach ([['Dune', 'D-1', 9.99], ['Foundation', 'F-1', 7.99], ['Hyperion', 'H-1', 12.50]] as [$t, $isbn, $price]) {
            $service->save($this->books, $this->site, null, [
                'data' => ['title' => $t, 'isbn' => $isbn, 'price' => $price], 'status' => 'published',
            ]);
        }

        $base = "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records";

        $this->actingAsOwner()->getJson("{$base}?q=dune")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Dune');

        $sorted = $this->actingAsOwner()->getJson("{$base}?sort=data.price&direction=desc")->assertOk();
        $this->assertSame(['Hyperion', 'Dune', 'Foundation'], collect($sorted->json('data'))->pluck('title')->all());

        $page = $this->actingAsOwner()->getJson("{$base}?per_page=5&sort=title")->assertOk();
        $this->assertSame(3, $page->json('meta.total'));
    }

    public function test_bulk_publish_and_bulk_delete(): void
    {
        $service = app(RecordService::class);
        $ids = collect(['A', 'B', 'C'])->map(fn ($t, $i) => $service->save($this->books, $this->site, null, [
            'data' => ['title' => $t, 'isbn' => "BULK-{$i}"],
        ])->id)->all();

        $base = "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/records";

        $this->actingAsOwner()->postJson("{$base}/bulk", ['action' => 'publish', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.done', 3);
        $this->assertSame(3, Record::where('collection_id', $this->books->id)->where('status', 'published')->count());

        $this->actingAsOwner()->postJson("{$base}/bulk", ['action' => 'delete', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.done', 3);
        $this->assertSame(0, Record::where('collection_id', $this->books->id)->count());
    }

    public function test_record_slugs_get_unique_suffix(): void
    {
        $service = app(RecordService::class);
        $first = $service->save($this->books, $this->site, null, ['data' => ['title' => 'Dune', 'isbn' => 'S-1']]);
        $second = $service->save($this->books, $this->site, null, ['data' => ['title' => 'Dune', 'isbn' => 'S-2']]);

        $this->assertSame('dune', $first->slug);
        $this->assertSame('dune-2', $second->slug);
    }
}
