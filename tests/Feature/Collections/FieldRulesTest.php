<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collections v3 — field-level validation rules (regex pattern, date bounds)
 * and schema default values applied on record creation.
 */
class FieldRulesTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function makeCollection(array $fields, array $extra = []): ContentCollection
    {
        return app(CollectionService::class)->create($this->site, array_merge([
            'name' => 'Widgets',
            'tier' => 'static',
            'schema' => [
                'fields' => array_merge([
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ], $fields),
                'title_field' => 'title',
            ],
        ], $extra));
    }

    public function test_text_pattern_rejects_nonmatching_and_uses_custom_message(): void
    {
        $collection = $this->makeCollection([[
            'key' => 'code', 'label' => 'Code', 'type' => 'text',
            'settings' => ['pattern' => '^[A-Z]{3}-\d{4}$', 'pattern_message' => 'Code looks like ABC-1234.'],
        ]]);

        $service = app(RecordService::class);

        try {
            $service->save($collection, $this->site, null, [
                'data' => ['title' => 'One', 'code' => 'nope'],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Code looks like ABC-1234.', $e->errors()['data.code'][0]);
        }

        $record = $service->save($collection, $this->site, null, [
            'data' => ['title' => 'Two', 'code' => 'ABC-1234'],
        ]);
        $this->assertSame('ABC-1234', $record->data['code']);
    }

    public function test_invalid_pattern_is_rejected_at_schema_save(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeCollection([[
            'key' => 'code', 'label' => 'Code', 'type' => 'text',
            'settings' => ['pattern' => '([unclosed'],
        ]]);
    }

    public function test_date_bounds_enforced(): void
    {
        $collection = $this->makeCollection([[
            'key' => 'available', 'label' => 'Available', 'type' => 'date',
            'settings' => ['min' => '2026-01-01', 'max' => '2026-12-31'],
        ]]);

        $service = app(RecordService::class);

        try {
            $service->save($collection, $this->site, null, [
                'data' => ['title' => 'One', 'available' => '2025-06-15'],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('data.available', $e->errors());
        }

        $record = $service->save($collection, $this->site, null, [
            'data' => ['title' => 'Two', 'available' => '2026-06-15'],
        ]);
        $this->assertSame('2026-06-15', $record->data['available']);
    }

    public function test_defaults_apply_on_create_for_omitted_keys_only(): void
    {
        $collection = $this->makeCollection([
            ['key' => 'status_note', 'label' => 'Note', 'type' => 'text', 'default' => 'fresh'],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'number', 'default' => 10],
        ]);

        $service = app(RecordService::class);

        // Omitted keys pick up defaults.
        $a = $service->save($collection, $this->site, null, ['data' => ['title' => 'A']]);
        $this->assertSame('fresh', $a->data['status_note']);
        $this->assertSame(10, $a->data['stock']);

        // Explicit values win.
        $b = $service->save($collection, $this->site, null, [
            'data' => ['title' => 'B', 'status_note' => 'aged', 'stock' => 3],
        ]);
        $this->assertSame('aged', $b->data['status_note']);
        $this->assertSame(3, $b->data['stock']);

        // Updates never re-apply defaults: full-replacement data without the
        // key simply drops it.
        $b2 = $service->save($collection, $this->site, $b, [
            'data' => ['title' => 'B'],
        ]);
        $this->assertArrayNotHasKey('status_note', $b2->data);
    }

    public function test_invalid_default_rejected_at_schema_save(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeCollection([[
            'key' => 'stock', 'label' => 'Stock', 'type' => 'number', 'default' => 'not-a-number',
        ]]);
    }

    public function test_default_not_allowed_on_asset_fields(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeCollection([[
            'key' => 'photo', 'label' => 'Photo', 'type' => 'image', 'default' => '0190a000-0000-7000-8000-000000000000',
        ]]);
    }
}
