<?php

namespace Tests\Feature\Collections;

use App\Models\Block;
use App\Models\ContentCollection;
use App\Models\Page;
use App\Models\Record;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Tests\TestCase;

/**
 * S6 — Database / Search / App wizards. Deterministic scaffolding over the
 * existing collection/page/template/block services.
 */
class AppWizardTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function artShopSpecs(): array
    {
        return [
            ['name' => 'Categories', 'hierarchical' => true, 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
            ]],
            ['name' => 'Artists', 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true],
                ['label' => 'Country', 'type' => 'select', 'options' => ['NL', 'JP', 'US']],
                ['label' => 'Portrait', 'type' => 'image'],
            ]],
            ['name' => 'Products', 'fields' => [
                ['label' => 'Title', 'type' => 'text', 'required' => true, 'searchable' => true],
                ['label' => 'Price', 'type' => 'price'],
                ['label' => 'Cover', 'type' => 'image'],
                ['label' => 'Artist', 'type' => 'relation', 'target' => 'Artists', 'mode' => 'one'],
                ['label' => 'Category', 'type' => 'relation', 'target' => 'Categories', 'mode' => 'one'],
            ]],
        ];
    }

    public function test_database_wizard_creates_collections_relations_and_hierarchy(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/wizard/database", ['collections' => $this->artShopSpecs()])
            ->assertStatus(201);

        $this->assertSame(3, ContentCollection::where('site_id', $this->site->id)->count());

        $categories = ContentCollection::where('name', 'Categories')->firstOrFail();
        $this->assertSame('parent', $categories->hierarchyField());

        $products = ContentCollection::where('name', 'Products')->firstOrFail();
        $artists = ContentCollection::where('name', 'Artists')->firstOrFail();
        $artistRel = collect($products->fields())->firstWhere('key', 'artist');
        $this->assertSame('relation', $artistRel['type']);
        $this->assertSame($artists->id, $artistRel['relation']['collection_id']);
        $this->assertSame('one', $artistRel['relation']['mode']);
    }

    public function test_database_wizard_rejects_unknown_relation_target(): void
    {
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/wizard/database", [
            'collections' => [
                ['name' => 'Products', 'fields' => [
                    ['label' => 'Title', 'type' => 'text'],
                    ['label' => 'Maker', 'type' => 'relation', 'target' => 'Ghosts'],
                ]],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, ContentCollection::where('site_id', $this->site->id)->count());
    }

    public function test_search_wizard_sets_flags_and_builds_page(): void
    {
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/wizard/database", [
            'collections' => [['name' => 'Products', 'fields' => [
                ['label' => 'Title', 'type' => 'text', 'required' => true],
                ['label' => 'Genre', 'type' => 'select', 'options' => ['Oil', 'Ink']],
            ]]],
        ])->assertStatus(201);
        $products = ContentCollection::where('name', 'Products')->firstOrFail();

        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/wizard/search", [
            'collection_id' => $products->id,
            'searchable' => ['title'],
            'facets' => ['genre'],
            'build_page' => true,
        ])->assertStatus(201);

        $products->refresh();
        $this->assertTrue(collect($products->fields())->firstWhere('key', 'title')['searchable']);
        $this->assertTrue(collect($products->fields())->firstWhere('key', 'genre')['facetable']);

        $page = Page::find($response->json('data.page.id'));
        $this->assertNotNull($page);
        $types = Block::whereMorphedTo('blockable', $page)->pluck('type');
        $this->assertTrue($types->contains('search-box'));
        $this->assertTrue($types->contains('facet-filter'));
        $this->assertTrue($types->contains('results-grid'));
    }

    public function test_app_wizard_scaffolds_collections_templates_pages_and_search(): void
    {
        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/wizard/app", [
            'collections' => $this->artShopSpecs(),
            'pages_for' => ['products', 'artists'],
            'search_for' => 'products',
        ])->assertStatus(201);

        $data = $response->json('data');
        $this->assertCount(3, $data['collections']);
        $this->assertCount(2, $data['index_pages']);      // products + artists
        $this->assertCount(2, $data['templates']);
        $this->assertNotNull($data['search_page']);

        // A record-single template exists and resolves for a product record
        $products = ContentCollection::where('name', 'Products')->firstOrFail();
        $template = ThemeTemplate::where('collection_id', $products->id)->where('type', 'record-single')->firstOrFail();
        $this->assertTrue($template->is_default);
        $moduleTypes = Block::whereMorphedTo('blockable', $template)->pluck('type');
        $this->assertTrue($moduleTypes->contains('record-title'));
        $this->assertTrue($moduleTypes->contains('record-image'));
        $this->assertTrue($moduleTypes->contains('field-value'));

        // Search flags auto-picked on products
        $products->refresh();
        $this->assertTrue(collect($products->fields())->firstWhere('key', 'title')['searchable']);
    }

    public function test_wizard_requires_admin(): void
    {
        $editor = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'editor']);
        $this->actingAs($editor)->postJson("/api/v1/sites/{$this->site->id}/wizard/database", [
            'collections' => [['name' => 'X', 'fields' => [['label' => 'A', 'type' => 'text']]]],
        ])->assertStatus(403);
    }
}
