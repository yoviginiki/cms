<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Domain\Publishing\Jobs\ProcessScheduledContentJob;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collections v3 — publish_at/unpublish_at windows processed by the scheduled
 * content job, and per-record seo_meta overrides in the published head.
 */
class RecordSchedulingSeoTest extends TestCase
{
    private Site $site;
    private ContentCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Shows',
            'tier' => 'static',
            'schema' => [
                'fields' => [['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true]],
                'title_field' => 'title',
            ],
        ]);
    }

    public function test_scheduled_publish_and_unpublish_flip_status(): void
    {
        $service = app(RecordService::class);

        $due = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Opens soon'],
            'status' => 'draft',
            'publish_at' => now()->subMinute()->toISOString(),
        ]);
        $expired = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Closing'],
            'status' => 'published',
            'unpublish_at' => now()->subMinute()->toISOString(),
        ]);
        $future = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Later'],
            'status' => 'draft',
            'publish_at' => now()->addDay()->toISOString(),
        ]);

        (new ProcessScheduledContentJob())();

        $this->assertSame('published', $due->fresh()->status);
        $this->assertNull($due->fresh()->publish_at);
        $this->assertSame('draft', $expired->fresh()->status);
        $this->assertSame('draft', $future->fresh()->status);
        $this->assertNotNull($future->fresh()->publish_at);
    }

    public function test_unpublish_before_publish_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(RecordService::class)->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Bad window'],
            'publish_at' => now()->addDay()->toISOString(),
            'unpublish_at' => now()->toISOString(),
        ]);
    }

    public function test_seo_meta_overrides_published_head(): void
    {
        $service = app(RecordService::class);
        $record = $service->save($this->collection, $this->site, null, [
            'data' => ['title' => 'Plain title'],
            'status' => 'published',
            'seo_meta' => [
                'title' => 'SEO title wins',
                'description' => 'Handwritten description.',
                'og_image' => 'not-a-uuid',
            ],
        ]);

        $this->assertSame('SEO title wins', $record->seo_meta['title']);
        $this->assertArrayNotHasKey('og_image', $record->seo_meta);

        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            app(CollectionPublishService::class)->buildRecordPage($this->site, $this->collection, $record->fresh(), $staging);
            $html = File::get($staging . '/shows/' . $record->slug . '/index.html');
            $this->assertStringContainsString('SEO title wins', $html);
            $this->assertStringContainsString('Handwritten description.', $html);
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }
    }
}
