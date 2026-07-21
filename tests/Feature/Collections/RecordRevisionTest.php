<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\RecordRevision;
use App\Models\Site;
use Tests\TestCase;

/**
 * Collections v3 — revision snapshots on save, the revisions API, restore,
 * duplicate, and bulk set_field.
 */
class RecordRevisionTest extends TestCase
{
    private Site $site;
    private ContentCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Books',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'pages', 'label' => 'Pages', 'type' => 'number'],
                ],
                'title_field' => 'title',
            ],
        ]);
    }

    public function test_save_writes_revisions_and_restore_returns_old_state(): void
    {
        $service = app(RecordService::class);
        $record = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'First edition', 'pages' => 100],
        ]);
        $service->save($this->collection, $this->site, $record, [
            'data' => ['title' => 'Second edition', 'pages' => 150],
        ]);

        $revisions = RecordRevision::where('record_id', $record->id)->orderBy('created_at')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame(['created', 'updated'], $revisions->pluck('event')->all());
        $this->assertSame('First edition', $revisions[0]->data['title']);

        // Restore via API
        $res = $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/records/{$record->id}/revisions/{$revisions[0]->id}/restore"
        );
        $res->assertOk();
        $this->assertSame('First edition', $res->json('data.data.title'));
        $this->assertSame(100, $res->json('data.data.pages'));

        $this->assertSame(
            'restored',
            RecordRevision::where('record_id', $record->id)->orderByDesc('created_at')->value('event')
        );
    }

    public function test_revisions_endpoint_lists_newest_first(): void
    {
        $service = app(RecordService::class);
        $record = $service->save($this->collection, $this->site, null, ['data' => ['title' => 'A']]);
        $service->save($this->collection, $this->site, $record, ['data' => ['title' => 'B']]);

        $res = $this->actingAs($this->owner)->getJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/records/{$record->id}/revisions"
        );
        $res->assertOk();
        $this->assertSame('updated', $res->json('data.0.event'));
        $this->assertSame('created', $res->json('data.1.event'));
    }

    public function test_duplicate_copies_data_as_draft(): void
    {
        $service = app(RecordService::class);
        $record = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Original', 'pages' => 42],
            'status' => 'published',
        ]);

        $res = $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/records/{$record->id}/duplicate"
        );
        $res->assertCreated();
        $this->assertSame('Original (copy)', $res->json('data.title'));
        $this->assertSame('draft', $res->json('data.status'));
        $this->assertSame(42, $res->json('data.data.pages'));
        $this->assertNotSame($record->slug, $res->json('data.slug'));
    }

    public function test_bulk_set_field_updates_selected_and_reports_failures(): void
    {
        $service = app(RecordService::class);
        $a = $service->save($this->collection, $this->site, null, ['data' => ['title' => 'A', 'pages' => 1]]);
        $b = $service->save($this->collection, $this->site, null, ['data' => ['title' => 'B', 'pages' => 2]]);

        $res = $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/records/bulk",
            ['action' => 'set_field', 'ids' => [$a->id, $b->id], 'field' => 'pages', 'value' => 99]
        );
        $res->assertOk();
        $this->assertSame(2, $res->json('data.done'));
        $this->assertSame(99, $a->fresh()->data['pages']);
        $this->assertSame(99, $b->fresh()->data['pages']);

        // Invalid value → skipped with error, not a 500.
        $res2 = $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/records/bulk",
            ['action' => 'set_field', 'ids' => [$a->id], 'field' => 'pages', 'value' => 'not-a-number']
        );
        $res2->assertOk();
        $this->assertSame(0, $res2->json('data.done'));
        $this->assertCount(1, $res2->json('data.skipped'));
    }
}
