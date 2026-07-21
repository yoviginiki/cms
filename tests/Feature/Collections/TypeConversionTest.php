<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use Tests\TestCase;

/** Collections v3 — guided text → select conversion. */
class TypeConversionTest extends TestCase
{
    private Site $site;
    private ContentCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Plants',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'zone', 'label' => 'Zone', 'type' => 'text'],
                ],
                'title_field' => 'title',
            ],
        ]);

        $records = app(RecordService::class);
        foreach ([['Fern', 'shade'], ['Cactus', 'sun'], ['Moss', 'shade']] as [$t, $z]) {
            $records->save($this->collection, $this->site, null, ['data' => ['title' => $t, 'zone' => $z]]);
        }
    }

    public function test_preview_lists_distinct_values(): void
    {
        $res = $this->actingAs($this->owner)->getJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/convert-preview?field=zone"
        );
        $res->assertOk();
        $this->assertTrue($res->json('data.convertible'));
        $values = collect($res->json('data.distinct'))->pluck('value')->sort()->values()->all();
        $this->assertSame(['shade', 'sun'], $values);
    }

    public function test_convert_turns_text_into_select_with_options(): void
    {
        $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/convert",
            ['field' => 'zone', 'to' => 'select']
        )->assertOk();

        $field = $this->collection->fresh()->field('zone');
        $this->assertSame('select', $field['type']);
        $this->assertEqualsCanonicalizing(['shade', 'sun'], $field['options']);

        // Existing stored values still validate on next save.
        $record = \App\Models\Record::where('collection_id', $this->collection->id)->where('title', 'Fern')->first();
        $updated = app(RecordService::class)->save($this->collection->fresh(), $this->site, $record, [
            'data' => ['title' => 'Fern', 'zone' => 'shade'],
        ]);
        $this->assertSame('shade', $updated->data['zone']);
    }

    public function test_title_field_and_nontext_rejected(): void
    {
        $this->actingAs($this->owner)->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->collection->id}/convert",
            ['field' => 'missing', 'to' => 'select']
        )->assertStatus(422);
    }
}
