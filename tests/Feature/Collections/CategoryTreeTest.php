<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CategorySchemaResolver;
use App\Domain\Collections\Services\CollectionCategoryService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\CollectionCategoryNode;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collections category tree: arbitrary-depth nodes, per-node field schemas,
 * effective-schema inheritance, record assignment, and backward compatibility
 * (a collection with no tree behaves exactly as before).
 */
class CategoryTreeTest extends TestCase
{
    private Site $site;
    private ContentCollection $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->products = app(CollectionService::class)->create($this->site, [
            'name' => 'Products',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true],
                    ['key' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'select', 'options' => ['Acme', 'Globex'], 'facetable' => true],
                ],
                'title_field' => 'name',
            ],
        ]);
    }

    private function svc(): CollectionCategoryService
    {
        return app(CollectionCategoryService::class);
    }

    // ── Tree CRUD ───────────────────────────────────────────────────────────

    public function test_creates_arbitrary_depth_tree_with_counts(): void
    {
        $svc = $this->svc();
        $root = $svc->create($this->products, $this->site, ['name' => 'Cooling']);
        $mid = $svc->create($this->products, $this->site, ['name' => 'Compressors', 'parent_id' => $root->id]);
        $leaf = $svc->create($this->products, $this->site, ['name' => 'Scroll', 'parent_id' => $mid->id]);

        $this->assertSame(0, $root->depth);
        $this->assertSame(1, $mid->depth);
        $this->assertSame(2, $leaf->depth);

        // A record filed at the leaf shows in the leaf's count.
        app(RecordService::class)->save($this->products, $this->site, null, [
            'data' => ['name' => 'ZR Scroll 42'],
            'category_node_id' => $leaf->id,
        ]);

        $tree = $svc->tree($this->products);
        $this->assertCount(1, $tree);
        $this->assertSame('Cooling', $tree[0]['name']);
        $compressors = $tree[0]['children'][0];
        $scroll = $compressors['children'][0];
        $this->assertSame('Scroll', $scroll['name']);
        $this->assertSame(1, $scroll['record_count']);
    }

    public function test_sibling_slugs_are_deduped(): void
    {
        $svc = $this->svc();
        $a = $svc->create($this->products, $this->site, ['name' => 'Refrigerants']);
        $b = $svc->create($this->products, $this->site, ['name' => 'Refrigerants']);
        $this->assertNotSame($a->slug, $b->slug);
    }

    // ── Effective schema / inheritance ───────────────────────────────────────

    public function test_effective_schema_merges_base_ancestors_and_node_most_specific_wins(): void
    {
        $svc = $this->svc();
        $root = $svc->create($this->products, $this->site, [
            'name' => 'Compressors',
            'schema' => ['fields' => [['key' => 'displacement', 'label' => 'Displacement', 'type' => 'number']]],
        ]);
        $leaf = $svc->create($this->products, $this->site, [
            'name' => 'Semi-hermetic',
            'parent_id' => $root->id,
            'schema' => ['fields' => [
                ['key' => 'oil_type', 'label' => 'Oil type', 'type' => 'text'],
                // Override a base field by reusing its key
                ['key' => 'manufacturer', 'label' => 'Brand', 'type' => 'text'],
            ]],
        ]);

        $resolver = app(CategorySchemaResolver::class);
        $fields = $resolver->effectiveFields($this->products, $leaf->id);
        $byKey = collect($fields)->keyBy('key');

        // base fields present
        $this->assertTrue($byKey->has('name'));
        // ancestor field inherited
        $this->assertTrue($byKey->has('displacement'));
        // node field present
        $this->assertTrue($byKey->has('oil_type'));
        // override: manufacturer is now the node's text override, not the base select
        $this->assertSame('text', $byKey['manufacturer']['type']);
        $this->assertSame('Brand', $byKey['manufacturer']['label']);
    }

    public function test_no_node_yields_exactly_base_fields_backward_compat(): void
    {
        $resolver = app(CategorySchemaResolver::class);
        $this->assertSame($this->products->fields(), $resolver->effectiveFields($this->products, null));
    }

    public function test_record_under_node_validates_and_stores_node_field(): void
    {
        $svc = $this->svc();
        $node = $svc->create($this->products, $this->site, [
            'name' => 'Sensors',
            'schema' => ['fields' => [['key' => 'accuracy', 'label' => 'Accuracy', 'type' => 'number', 'required' => true]]],
        ]);

        // Missing the required node field is rejected.
        try {
            app(RecordService::class)->save($this->products, $this->site, null, [
                'data' => ['name' => 'PT100'],
                'category_node_id' => $node->id,
            ]);
            $this->fail('expected node-field validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('data.accuracy', $e->errors());
        }

        // Providing it stores the value + the node link.
        $record = app(RecordService::class)->save($this->products, $this->site, null, [
            'data' => ['name' => 'PT100', 'accuracy' => 0.5],
            'category_node_id' => $node->id,
        ]);
        $this->assertSame($node->id, $record->category_node_id);
        $this->assertSame(0.5, $record->data['accuracy']);
    }

    public function test_node_from_another_collection_is_rejected(): void
    {
        $other = app(CollectionService::class)->create($this->site, [
            'name' => 'Other',
            'schema' => ['fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text']], 'title_field' => 'name'],
        ]);
        $foreign = $this->svc()->create($other, $this->site, ['name' => 'Foreign']);

        $this->expectException(ValidationException::class);
        app(RecordService::class)->save($this->products, $this->site, null, [
            'data' => ['name' => 'X'],
            'category_node_id' => $foreign->id,
        ]);
    }

    // ── Move / delete ────────────────────────────────────────────────────────

    public function test_move_rejects_cycle(): void
    {
        $svc = $this->svc();
        $root = $svc->create($this->products, $this->site, ['name' => 'A']);
        $child = $svc->create($this->products, $this->site, ['name' => 'B', 'parent_id' => $root->id]);

        $this->expectException(ValidationException::class);
        $svc->move($root, $child->id, null); // moving A under its own descendant B
    }

    public function test_delete_reparents_children_and_records(): void
    {
        $svc = $this->svc();
        $root = $svc->create($this->products, $this->site, ['name' => 'Root']);
        $mid = $svc->create($this->products, $this->site, ['name' => 'Mid', 'parent_id' => $root->id]);
        $leaf = $svc->create($this->products, $this->site, ['name' => 'Leaf', 'parent_id' => $mid->id]);

        $record = app(RecordService::class)->save($this->products, $this->site, null, [
            'data' => ['name' => 'Item'], 'category_node_id' => $mid->id,
        ]);

        $svc->delete($mid, 'reparent');

        // Leaf now hangs off Root; record moved up to Root.
        $this->assertSame($root->id, $leaf->fresh()->parent_id);
        $this->assertSame(1, $leaf->fresh()->depth);
        $this->assertSame($root->id, $record->fresh()->category_node_id);
        $this->assertNull(CollectionCategoryNode::find($mid->id));
    }

    public function test_delete_cascade_unfiles_records(): void
    {
        $svc = $this->svc();
        $root = $svc->create($this->products, $this->site, ['name' => 'Root']);
        $child = $svc->create($this->products, $this->site, ['name' => 'Child', 'parent_id' => $root->id]);
        $record = app(RecordService::class)->save($this->products, $this->site, null, [
            'data' => ['name' => 'Item'], 'category_node_id' => $child->id,
        ]);

        $result = $svc->delete($root, 'cascade');

        $this->assertSame(2, $result['deleted']);
        $this->assertNull($record->fresh()->category_node_id); // kept, unfiled
        $this->assertNotNull(Record::find($record->id));
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function test_api_tree_create_and_effective_schema(): void
    {
        $create = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/collections/{$this->products->id}/category-nodes", [
                'name' => 'Tools',
                'schema' => ['fields' => [['key' => 'weight', 'label' => 'Weight', 'type' => 'number']]],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/collections/{$this->products->id}/category-tree")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tools');

        $eff = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/collections/{$this->products->id}/category-nodes/{$create['id']}/effective-schema")
            ->assertOk()
            ->json('data.fields');
        $this->assertContains('weight', array_column($eff, 'key'));
        $this->assertContains('name', array_column($eff, 'key'));
    }

    // ── Migration command ────────────────────────────────────────────────────

    public function test_migrate_tree_command_builds_tree_from_flat_values(): void
    {
        // Two records with flat category/subcategory values.
        foreach ([['Cooling', 'Compressors'], ['Cooling', 'Fans'], ['Heating', 'Boilers']] as [$cat, $sub]) {
            app(RecordService::class)->save($this->products, $this->site, null, [
                'data' => ['name' => "$sub item"],
            ]);
        }
        // Rewrite their data to carry flat cat/sub (schema doesn't have them, but
        // migrate reads raw data keys) — set directly.
        $records = Record::where('collection_id', $this->products->id)->get();
        $pairs = [['Cooling', 'Compressors'], ['Cooling', 'Fans'], ['Heating', 'Boilers']];
        foreach ($records as $i => $r) {
            $r->update(['data' => array_merge($r->data, ['category' => $pairs[$i][0], 'subcategory' => $pairs[$i][1]])]);
        }

        $this->artisan('collections:migrate-tree', [
            '--site' => $this->site->slug,
            '--collection' => $this->products->slug,
        ])->assertSuccessful();

        // 2 roots (Cooling, Heating), 3 leaves.
        $roots = CollectionCategoryNode::where('collection_id', $this->products->id)->whereNull('parent_id')->pluck('name')->sort()->values();
        $this->assertEquals(['Cooling', 'Heating'], $roots->all());
        $this->assertSame(3, CollectionCategoryNode::where('collection_id', $this->products->id)->whereNotNull('parent_id')->count());

        // Every record filed under a leaf.
        $this->assertSame(0, Record::where('collection_id', $this->products->id)->whereNull('category_node_id')->count());

        // Idempotent: a second run creates nothing new.
        $before = CollectionCategoryNode::where('collection_id', $this->products->id)->count();
        $this->artisan('collections:migrate-tree', [
            '--site' => $this->site->slug,
            '--collection' => $this->products->slug,
        ])->assertSuccessful();
        $this->assertSame($before, CollectionCategoryNode::where('collection_id', $this->products->id)->count());
    }
}
