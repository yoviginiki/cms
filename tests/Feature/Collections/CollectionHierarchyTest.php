<?php

namespace Tests\Feature\Collections;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * S3 — hierarchical collections: the hierarchy_field setting, cycle guard,
 * nested published URLs with breadcrumbs/children nav, descendant staleness
 * on URL shifts, and the record-loop children/related sources.
 */
class CollectionHierarchyTest extends TestCase
{
    private Site $site;
    private ContentCollection $categories;
    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->categories = $this->makeCategories();
        $this->staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($this->staging);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-builds'));
        parent::tearDown();
    }

    private function makeCategories(): ContentCollection
    {
        $service = app(CollectionService::class);
        $collection = $service->create($this->site, [
            'name' => 'Categories',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ],
                'title_field' => 'name',
            ],
        ]);

        // Self-relations require the collection to exist → two-step setup.
        return $service->update($collection, $this->site, [
            'name' => 'Categories',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['key' => 'parent', 'label' => 'Parent', 'type' => 'relation', 'relation' => ['collection_id' => $collection->id, 'mode' => 'one']],
                ],
                'title_field' => 'name',
            ],
            'settings' => ['hierarchy_field' => 'parent'],
        ])['collection'];
    }

    private function category(string $name, ?Record $parent = null, string $status = 'published'): Record
    {
        return app(RecordService::class)->save($this->categories, $this->site, null, [
            'data' => ['name' => $name],
            'status' => $status,
            'relations' => $parent ? ['parent' => [['id' => $parent->id]]] : [],
        ]);
    }

    // ─── Setting validation ─────────────────────────────────────────────

    public function test_hierarchy_field_must_be_a_self_relation_in_one_mode(): void
    {
        $service = app(CollectionService::class);

        // Non-relation field rejected
        try {
            $service->update($this->categories, $this->site, [
                'name' => 'Categories',
                'schema' => $this->categories->schema,
                'settings' => ['hierarchy_field' => 'name'],
            ]);
            $this->fail('expected rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings.hierarchy_field', $e->errors());
        }

        // Unknown field rejected
        try {
            $service->update($this->categories, $this->site, [
                'name' => 'Categories',
                'schema' => $this->categories->schema,
                'settings' => ['hierarchy_field' => 'nope'],
            ]);
            $this->fail('expected rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings.hierarchy_field', $e->errors());
        }

        // Explicit disable accepted
        $result = $service->update($this->categories, $this->site, [
            'name' => 'Categories',
            'schema' => $this->categories->schema,
            'settings' => ['hierarchy_field' => null],
        ]);
        $this->assertNull($result['collection']->hierarchyField());
    }

    public function test_dangling_hierarchy_setting_reads_as_flat(): void
    {
        // Simulate a later schema edit that removed the parent field: the
        // stored setting survives but hierarchyField() must refuse it.
        $this->categories->update(['settings' => ['hierarchy_field' => 'ghost']]);
        $this->assertNull($this->categories->fresh()->hierarchyField());
    }

    // ─── Cycle guard ────────────────────────────────────────────────────

    public function test_cycle_and_self_parent_rejected(): void
    {
        $a = $this->category('Alpha');
        $b = $this->category('Beta', $a);
        $service = app(RecordService::class);

        // Self-parent
        try {
            $service->save($this->categories, $this->site, $a, ['relations' => ['parent' => [['id' => $a->id]]]]);
            $this->fail('self-parent accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('own parent', json_encode($e->errors()));
        }

        // A → B while B → A
        try {
            $service->save($this->categories, $this->site, $a, ['relations' => ['parent' => [['id' => $b->id]]]]);
            $this->fail('cycle accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('loop', json_encode($e->errors()));
        }
    }

    public function test_tree_depth_is_capped(): void
    {
        $parent = $this->category('Level 1');
        for ($i = 2; $i <= RecordService::MAX_TREE_DEPTH; $i++) {
            $parent = $this->category("Level {$i}", $parent);
        }

        $this->expectException(ValidationException::class);
        $this->category('Too deep', $parent);
    }

    // ─── Published output ───────────────────────────────────────────────

    public function test_nested_urls_breadcrumbs_children_sitemap_and_index(): void
    {
        $root = $this->category('Painting');
        $mid = $this->category('Oil', $root);
        $leaf = $this->category('Impasto', $mid);

        $publisher = app(CollectionPublishService::class);
        $publisher->buildCollection($this->site, $this->categories->fresh(), $this->staging);

        // Nested paths on disk
        $this->assertFileExists("{$this->staging}/categories/painting/index.html");
        $this->assertFileExists("{$this->staging}/categories/painting/oil/index.html");
        $this->assertFileExists("{$this->staging}/categories/painting/oil/impasto/index.html");

        // Leaf page: breadcrumb links every ancestor
        $leafHtml = File::get("{$this->staging}/categories/painting/oil/impasto/index.html");
        $this->assertStringContainsString('href="/categories/painting/"', $leafHtml);
        $this->assertStringContainsString('href="/categories/painting/oil/"', $leafHtml);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $leafHtml);

        // Root page: children nav lists Oil
        $rootHtml = File::get("{$this->staging}/categories/painting/index.html");
        $this->assertStringContainsString('href="/categories/painting/oil/"', $rootHtml);
        $this->assertStringContainsString('In Painting', $rootHtml);

        // Search index rows carry nested URLs
        $index = json_decode(File::get("{$this->staging}/categories/index.json"), true);
        $shard = json_decode(File::get($this->staging . parse_url($index['shards'][0], PHP_URL_PATH)), true);
        $urls = array_column($shard, 'u');
        $this->assertContains('/categories/painting/oil/impasto/', $urls);

        // Sitemap
        $sitemapUrls = array_column($publisher->sitemapUrls($this->site), 'path');
        $this->assertContains('/categories/painting/oil/', $sitemapUrls);
    }

    public function test_draft_parent_is_skipped_from_urls(): void
    {
        $draft = $this->category('Hidden', null, 'draft');
        $child = $this->category('Visible', $draft);

        $publisher = app(CollectionPublishService::class);
        $publisher->buildCollection($this->site, $this->categories->fresh(), $this->staging);

        $this->assertFileExists("{$this->staging}/categories/visible/index.html");
    }

    // ─── Descendant staleness ───────────────────────────────────────────

    public function test_ancestor_url_change_flags_descendants(): void
    {
        $root = $this->category('Sculpture');
        $mid = $this->category('Bronze', $root);
        $leaf = $this->category('Cast', $mid);

        // Clear flags as a publish would
        Record::whereIn('id', [$root->id, $mid->id, $leaf->id])->update(['needs_republish' => false]);

        app(RecordService::class)->save($this->categories, $this->site, $root->fresh(), ['slug' => 'sculpture-3d']);

        $this->assertTrue($mid->fresh()->needs_republish);
        $this->assertSame('ancestor_moved', $mid->fresh()->needs_republish_reason);
        $this->assertTrue($leaf->fresh()->needs_republish);

        // No-op save does NOT flag descendants
        Record::whereIn('id', [$mid->id, $leaf->id])->update(['needs_republish' => false]);
        app(RecordService::class)->save($this->categories, $this->site, $root->fresh(), ['data' => ['name' => 'Sculpture']]);
        $this->assertFalse($mid->fresh()->needs_republish);
    }

    // ─── record-loop sources ────────────────────────────────────────────

    public function test_record_loop_children_and_related_sources(): void
    {
        $root = $this->category('Prints');
        $child = $this->category('Etching', $root);

        // Products with a relation into categories
        $service = app(CollectionService::class);
        $products = $service->create($this->site, [
            'name' => 'Products',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'relation', 'relation' => ['collection_id' => $this->categories->id, 'mode' => 'one']],
                ],
                'title_field' => 'title',
            ],
        ]);
        app(RecordService::class)->save($products, $this->site, null, [
            'data' => ['title' => 'Harbor Etching No. 4'],
            'status' => 'published',
            'relations' => ['category' => [['id' => $root->id]]],
        ]);

        $template = ThemeTemplate::create([
            'site_id' => $this->site->id,
            'name' => 'Category page',
            'slug' => 'category-page',
            'type' => 'record-single',
            'collection_id' => $this->categories->id,
            'is_default' => true,
        ]);
        app(BlockService::class)->syncBlocks($template, [
            ['type' => 'record-title', 'order' => 0, 'data' => ['tag' => 'h1']],
            ['type' => 'record-loop', 'order' => 1, 'data' => ['sourceMode' => 'children']],
            ['type' => 'record-loop', 'order' => 2, 'data' => [
                'sourceMode' => 'related',
                'relatedCollectionId' => $products->id,
                'relatedFieldKey' => 'category',
            ]],
        ]);

        app(CollectionPublishService::class)->buildRecordPage($this->site, $this->categories->fresh(), $root->fresh(), $this->staging);

        $html = File::get("{$this->staging}/categories/prints/index.html");
        $this->assertStringContainsString('Etching</a>', $html);                 // children source
        $this->assertStringContainsString('Harbor Etching No. 4', $html);        // related source
        $this->assertStringContainsString('href="/categories/prints/etching/"', $html);
    }

    // ─── Admin list API ─────────────────────────────────────────────────

    public function test_records_index_returns_parent_id(): void
    {
        $root = $this->category('Ceramics');
        $child = $this->category('Stoneware', $root);

        $rows = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/collections/{$this->categories->id}/records?per_page=100")
            ->assertOk()
            ->json('data');

        $byId = collect($rows)->keyBy('id');
        $this->assertNull($byId[$root->id]['parent_id']);
        $this->assertSame($root->id, $byId[$child->id]['parent_id']);
    }
}
