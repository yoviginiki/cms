<?php

namespace Tests\Feature\Collections;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Collections\Queries\QuerySentence;
use App\Domain\Collections\Queries\QueryRunner;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Page;
use App\Models\Record;
use App\Models\SavedQuery;
use App\Models\Site;
use Tests\TestCase;

/**
 * Track G-Q1 — Saved Queries core: definition validation, the Simple-mode
 * compiler (filters incl. relation hop, OR groups, sort, aggregations),
 * sentence preview, admin CRUD + role wall, the public endpoint with
 * declared typed params, blocks as data sources, and the staleness cascade
 * (price change → embedding page flagged).
 */
class SavedQueryTest extends TestCase
{
    private Site $site;
    private ContentCollection $suppliers;
    private ContentCollection $parts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);

        $cs = app(CollectionService::class);
        $this->suppliers = $cs->create($this->site, ['name' => 'Suppliers', 'schema' => ['fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'lead_time', 'label' => 'Lead time', 'type' => 'number'],
        ], 'title_field' => 'name']]);
        $this->parts = $cs->create($this->site, ['name' => 'Parts', 'schema' => ['fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true],
            ['key' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'select', 'facetable' => true, 'options' => ['Carrier', 'Daikin']],
            ['key' => 'price', 'label' => 'Price', 'type' => 'price'],
            ['key' => 'in_stock', 'label' => 'In stock', 'type' => 'boolean', 'facetable' => true],
            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'relation', 'relation' => ['collection_id' => $this->suppliers->id, 'mode' => 'many']],
        ], 'title_field' => 'name']]);

        $rs = app(RecordService::class);
        $fast = $rs->save($this->suppliers, $this->site, null, ['status' => 'published', 'data' => ['name' => 'FastCo', 'lead_time' => 3]]);
        $slow = $rs->save($this->suppliers, $this->site, null, ['status' => 'published', 'data' => ['name' => 'SlowCo', 'lead_time' => 20]]);

        $seed = [
            ['Compressor A', 'Carrier', 30, true, $fast->id],
            ['Compressor B', 'Carrier', 80, true, $slow->id],
            ['Fan C', 'Daikin', 45, true, $slow->id],
            ['Valve D', 'Daikin', 20, false, $fast->id],
            ['Coil E', 'Carrier', 15, true, null],
        ];
        foreach ($seed as [$name, $mfr, $price, $stock, $supplierId]) {
            $rs->save($this->parts, $this->site, null, [
                'status' => 'published',
                'data' => ['name' => $name, 'manufacturer' => $mfr, 'price' => $price, 'in_stock' => $stock],
                'relations' => $supplierId ? ['supplier' => [['id' => $supplierId]]] : [],
            ]);
        }
    }

    /** The spec's acceptance definition. */
    private function acceptanceDefinition(): array
    {
        return [
            'collection_id' => $this->parts->id,
            'filters' => ['op' => 'and', 'children' => [
                ['field' => 'in_stock', 'operator' => 'eq', 'value' => true],
                ['field' => 'price', 'operator' => 'lt', 'value' => 50],
            ]],
            'aggregate' => [
                'group_by' => 'manufacturer',
                'metrics' => [['fn' => 'count'], ['fn' => 'avg', 'field' => 'price']],
            ],
        ];
    }

    public function test_validator_rejects_bad_definitions(): void
    {
        $make = fn (array $def) => $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/saved-queries",
            ['name' => 'Bad', 'definition' => $def + ['collection_id' => $this->parts->id]],
        );

        // unknown field
        $make(['filters' => ['op' => 'and', 'children' => [['field' => 'nope', 'operator' => 'eq', 'value' => 1]]]])->assertStatus(422);
        // operator/type mismatch (contains on boolean)
        $make(['filters' => ['op' => 'and', 'children' => [['field' => 'in_stock', 'operator' => 'contains', 'value' => 'x']]]])->assertStatus(422);
        // relation depth wall: two hops
        $make(['filters' => ['op' => 'and', 'children' => [['field' => 'supplier.owner.name', 'operator' => 'eq', 'value' => 'x']]]])->assertStatus(422);
        // avg over non-numeric
        $make(['aggregate' => ['group_by' => null, 'metrics' => [['fn' => 'avg', 'field' => 'name']]]])->assertStatus(422);
        // group nesting > 3
        $g = ['field' => 'price', 'operator' => 'lt', 'value' => 1];
        $deep = ['op' => 'and', 'children' => [['op' => 'or', 'children' => [['op' => 'and', 'children' => [['op' => 'or', 'children' => [$g]]]]]]]];
        $make(['filters' => $deep])->assertStatus(422);
    }

    public function test_acceptance_query_aggregates_and_reads_as_sentence(): void
    {
        $definition = app(\App\Domain\Collections\Queries\SavedQueryValidator::class)
            ->validate($this->acceptanceDefinition(), $this->site);

        // Sentence
        $sentence = app(QuerySentence::class)->describe($definition, $this->parts);
        $this->assertSame(
            'Show parts, where In stock is yes and Price is under 50, grouped by manufacturer, showing count and average price.',
            $sentence,
        );

        // Result: in-stock under 50 → Compressor A (Carrier 30), Fan C (Daikin 45), Coil E (Carrier 15)
        $result = app(\App\Domain\Collections\Queries\SimpleQueryCompiler::class)->run($this->parts, $definition);
        $this->assertSame('table', $result['type']);
        $rows = collect($result['rows'])->keyBy('group');
        $this->assertSame(2, $rows['Carrier']['count']);
        $this->assertEqualsWithDelta(22.5, $rows['Carrier']['avg_price'], 0.001);
        $this->assertSame(1, $rows['Daikin']['count']);
        $this->assertEqualsWithDelta(45.0, $rows['Daikin']['avg_price'], 0.001);
    }

    public function test_relation_hop_filter_and_or_groups(): void
    {
        $runner = fn (array $filters) => app(\App\Domain\Collections\Queries\SimpleQueryCompiler::class)->run(
            $this->parts,
            app(\App\Domain\Collections\Queries\SavedQueryValidator::class)->validate([
                'collection_id' => $this->parts->id, 'filters' => $filters,
                'sort' => [['field' => 'title', 'direction' => 'asc']],
            ], $this->site),
        );

        // supplier.lead_time < 7 → parts supplied by FastCo: Compressor A, Valve D
        $result = $runner(['op' => 'and', 'children' => [['field' => 'supplier.lead_time', 'operator' => 'lt', 'value' => 7]]]);
        $this->assertSame(['Compressor A', 'Valve D'], $result['rows']->pluck('title')->all());

        // OR group: price < 20 OR manufacturer = Daikin
        $result = $runner(['op' => 'or', 'children' => [
            ['field' => 'price', 'operator' => 'lt', 'value' => 20],
            ['field' => 'manufacturer', 'operator' => 'eq', 'value' => 'Daikin'],
        ]]);
        $this->assertSame(['Coil E', 'Fan C', 'Valve D'], $result['rows']->pluck('title')->all());
    }

    public function test_authoring_is_admin_or_owner_only(): void
    {
        $payload = ['name' => 'Nope', 'definition' => ['collection_id' => $this->parts->id]];

        $this->actingAsEditor()->postJson("/api/v1/sites/{$this->site->id}/saved-queries", $payload)->assertStatus(403);
        $this->actingAsAdmin()->postJson("/api/v1/sites/{$this->site->id}/saved-queries", $payload)->assertStatus(201);
    }

    public function test_preview_endpoint_returns_sentence_and_sample_rows(): void
    {
        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/saved-queries/preview", [
            'definition' => [
                'collection_id' => $this->parts->id,
                'filters' => ['op' => 'and', 'children' => [['field' => 'in_stock', 'operator' => 'eq', 'value' => true]]],
                'sort' => [['field' => 'price', 'direction' => 'asc']],
            ],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('where In stock is yes', $response->json('data.sentence'));
        $this->assertSame('Coil E', $response->json('data.result.rows.0.title'));
        $this->assertSame(4, $response->json('data.result.total'));
    }

    public function test_public_endpoint_accepts_only_declared_typed_params(): void
    {
        $query = $this->createSavedQuery([
            'name' => 'Cheap parts',
            'is_public' => true,
            'public_params' => [['key' => 'max_price', 'type' => 'number', 'required' => false, 'default' => 100]],
            'definition' => [
                'collection_id' => $this->parts->id,
                'filters' => ['op' => 'and', 'children' => [['field' => 'price', 'operator' => 'lt', 'value' => ['param' => 'max_price']]]],
                'sort' => [['field' => 'title', 'direction' => 'asc']],
            ],
        ]);

        $base = "/api/v1/public/{$this->site->id}/queries/{$query->slug}";

        // default param (100) → all 5
        $this->getJson($base)->assertOk()->assertJsonPath('data.total', 5);
        // declared param narrows
        $this->getJson("{$base}?max_price=25")->assertOk()->assertJsonPath('data.total', 2);
        // wrong type
        $this->getJson("{$base}?max_price=abc")->assertStatus(422);
        // undeclared param rejected
        $this->getJson("{$base}?max_price=25&evil=1")->assertStatus(422);

        // non-public query → 404
        $private = $this->createSavedQuery(['name' => 'Private', 'is_public' => false, 'definition' => ['collection_id' => $this->parts->id]]);
        $this->getJson("/api/v1/public/{$this->site->id}/queries/{$private->slug}")->assertStatus(404);
    }

    public function test_query_table_renders_at_publish_and_price_change_flags_page(): void
    {
        $query = $this->createSavedQuery([
            'name' => 'Parts by manufacturer',
            'definition' => $this->acceptanceDefinition(),
        ]);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'title' => 'Dashboard']);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'query-table', 'order' => 0, 'data' => ['queryId' => $query->id]],
            ['type' => 'query-stat', 'order' => 1, 'data' => ['queryId' => $query->id, 'label' => 'Groups']],
        ]);

        $html = app(\App\Domain\Publishing\Services\BuildPageService::class)->build($page, null, $this->site);
        $this->assertStringContainsString('Carrier', $html);
        $this->assertStringContainsString('22.50', $html);   // avg price rendered
        $this->assertStringContainsString('Manufacturer', $html);

        // Price change → record → collection stale → query (intermediate) → page flagged.
        $page->forceFill(['needs_republish' => false])->save();
        $record = Record::where('collection_id', $this->parts->id)->where('title', 'Coil E')->firstOrFail();
        $data = $record->data;
        $data['price'] = 25;
        app(RecordService::class)->save($this->parts, $this->site, $record, ['data' => $data]);

        $this->assertTrue($page->fresh()->needs_republish, 'page embedding the query must be flagged stale');
    }

    public function test_record_loop_accepts_saved_query_source(): void
    {
        $query = $this->createSavedQuery([
            'name' => 'In stock only',
            'definition' => [
                'collection_id' => $this->parts->id,
                'filters' => ['op' => 'and', 'children' => [['field' => 'in_stock', 'operator' => 'eq', 'value' => true]]],
                'sort' => [['field' => 'price', 'direction' => 'asc']],
            ],
        ]);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'record-loop', 'order' => 0, 'data' => ['queryId' => $query->id, 'cardFields' => ['price']]],
        ]);

        $html = app(\App\Domain\Publishing\Services\BuildPageService::class)->build($page, null, $this->site);
        $this->assertStringContainsString('Coil E', $html);
        $this->assertStringNotContainsString('Valve D', $html); // out of stock
    }

    public function test_delete_protection_when_used_by_a_page(): void
    {
        $query = $this->createSavedQuery(['name' => 'Used', 'definition' => ['collection_id' => $this->parts->id]]);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'query-stat', 'order' => 0, 'data' => ['queryId' => $query->id]],
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/saved-queries/{$query->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/saved-queries/{$query->id}?force=1")
            ->assertOk();
        $this->assertDatabaseMissing('saved_queries', ['id' => $query->id]);
    }

    private function createSavedQuery(array $payload): SavedQuery
    {
        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/saved-queries", $payload);
        $response->assertStatus(201);

        return SavedQuery::findOrFail($response->json('data.id'));
    }
}
