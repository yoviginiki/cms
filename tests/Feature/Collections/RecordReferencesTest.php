<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\RecordService;
use App\Models\Asset;
use App\Models\ContentCollection;
use App\Models\EntityReference;
use App\Models\Site;
use Tests\TestCase;

/**
 * Records participate in the entity_references graph: asset fields protect
 * assets from deletion, relation edges exist as record→record embeds, and
 * deleting a record clears its outgoing edges.
 */
class RecordReferencesTest extends TestCase
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

    public function test_record_image_field_protects_asset_from_deletion(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        app(RecordService::class)->save($this->books, $this->site, null, [
            'data' => ['title' => 'Dune', 'isbn' => 'R-1', 'cover' => $asset->id],
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/assets/{$asset->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1)
            ->assertJsonPath('sources.0.type', 'record');

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_relations_create_record_embeds_edges(): void
    {
        $service = app(RecordService::class);
        $asimov = $service->save($this->authors, $this->site, null, ['data' => ['name' => 'Isaac Asimov']]);
        $book = $service->save($this->books, $this->site, null, [
            'data' => ['title' => 'Foundation', 'isbn' => 'R-2'],
            'relations' => ['author' => [['id' => $asimov->id]]],
        ]);

        $this->assertDatabaseHas('entity_references', [
            'site_id' => $this->site->id,
            'source_type' => 'record',
            'source_id' => $book->id,
            'target_type' => 'record',
            'target_id' => $asimov->id,
            'kind' => 'embeds',
        ]);

        // Usage endpoint resolves the referring record.
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/references/usage?target_type=record&target_id={$asimov->id}")
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_record_delete_via_api_requires_force_when_referenced(): void
    {
        $service = app(RecordService::class);
        $asimov = $service->save($this->authors, $this->site, null, ['data' => ['name' => 'Isaac Asimov']]);
        $service->save($this->books, $this->site, null, [
            'data' => ['title' => 'Foundation', 'isbn' => 'R-3'],
            'relations' => ['author' => [['id' => $asimov->id]]],
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/collections/{$this->authors->id}/records/{$asimov->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/collections/{$this->authors->id}/records/{$asimov->id}?force=1")
            ->assertOk();

        $this->assertDatabaseMissing('records', ['id' => $asimov->id]);
    }

    public function test_deleting_record_clears_its_outgoing_edges(): void
    {
        $service = app(RecordService::class);
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $book = $service->save($this->books, $this->site, null, [
            'data' => ['title' => 'Dune', 'isbn' => 'R-4', 'cover' => $asset->id],
        ]);

        $this->assertSame(1, EntityReference::forSource('record', $book->id)->count());

        $service->delete($book, $this->site);

        $this->assertSame(0, EntityReference::forSource('record', $book->id)->count());
    }

    public function test_gallery_field_emits_one_edge_per_asset(): void
    {
        $galleryCollection = app(\App\Domain\Collections\Services\CollectionService::class)->create($this->site, [
            'name' => 'Portfolios',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['key' => 'photos', 'label' => 'Photos', 'type' => 'gallery'],
                ],
                'title_field' => 'name',
            ],
        ]);

        $assets = Asset::factory()->count(3)->create(['site_id' => $this->site->id]);

        $record = app(RecordService::class)->save($galleryCollection, $this->site, null, [
            'data' => ['name' => 'Studio set', 'photos' => $assets->pluck('id')->all()],
        ]);

        $this->assertSame(3, EntityReference::forSource('record', $record->id)->where('target_type', 'asset')->count());
    }
}
