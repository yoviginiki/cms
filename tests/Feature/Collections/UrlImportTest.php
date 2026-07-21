<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collections v3 — scheduled URL imports: settings validation and the
 * fetch command importing CSV (export format) via header-keyed mapping.
 */
class UrlImportTest extends TestCase
{
    private Site $site;
    private ContentCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Stock',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'sku', 'label' => 'SKU', 'type' => 'sku', 'unique' => true],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                ],
                'title_field' => 'title',
            ],
        ]);
    }

    public function test_import_settings_validated(): void
    {
        $service = app(CollectionService::class);

        try {
            $service->update($this->collection, $this->site, [
                'name' => 'Stock',
                'schema' => $this->collection->schema,
                'settings' => ['import_url' => 'http://insecure.example/feed.csv'],
            ]);
            $this->fail('Expected ValidationException for http URL');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings.import_url', $e->errors());
        }

        try {
            $service->update($this->collection, $this->site, [
                'name' => 'Stock',
                'schema' => $this->collection->schema,
                'settings' => ['import_url' => 'https://ok.example/feed.csv', 'import_key' => 'qty'],
            ]);
            $this->fail('Expected ValidationException for non-unique key');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings.import_key', $e->errors());
        }

        $updated = $service->update($this->collection, $this->site, [
            'name' => 'Stock',
            'schema' => $this->collection->schema,
            'settings' => [
                'import_url' => 'https://ok.example/feed.csv',
                'import_schedule' => 'daily',
                'import_key' => 'sku',
                'import_status' => 'published',
            ],
        ]);
        $collection = $updated['collection'] ?? $updated;
        $fresh = $this->collection->fresh();
        $this->assertSame('https://ok.example/feed.csv', $fresh->settings['import_url']);
        $this->assertSame('sku', $fresh->settings['import_key']);
    }

    public function test_fetch_command_imports_and_upserts_by_header_mapping(): void
    {
        app(CollectionService::class)->update($this->collection, $this->site, [
            'name' => 'Stock',
            'schema' => $this->collection->schema,
            'settings' => [
                'import_url' => 'https://feeds.example.com/stock.csv',
                'import_schedule' => 'hourly',
                'import_key' => 'sku',
                'import_status' => 'published',
            ],
        ]);

        Http::fake([
            'feeds.example.com/*' => Http::sequence()
                ->push("title,sku,qty\nWidget,W-1,5\nGadget,G-2,9\n")
                ->push("title,sku,qty\nWidget,W-1,7\n"),
        ]);

        $this->artisanFetch();

        $this->assertSame(2, Record::where('collection_id', $this->collection->id)->count());
        $w = Record::where('collection_id', $this->collection->id)->where('title', 'Widget')->first();
        $this->assertSame(5, $w->data['qty']);

        // Second run upserts by SKU (updates qty, no new rows).
        $this->artisanFetch();

        $this->assertSame(2, Record::where('collection_id', $this->collection->id)->count());
        $this->assertSame(7, $w->fresh()->data['qty']);
    }

    private function artisanFetch(): void
    {
        // --collection forces the run regardless of schedule cadence; the DNS
        // SSRF guard is skipped via config (Http::fake hosts don't resolve).
        config(['collections.import_skip_dns_guard' => true]);
        \Illuminate\Support\Facades\Artisan::call('collections:fetch-imports', ['--collection' => $this->collection->id]);
        // Test env queues to the database driver — drain inline.
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
    }
}
