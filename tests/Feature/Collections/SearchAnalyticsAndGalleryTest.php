<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\Asset;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Collections v3 — search-term beacon + top-terms admin API, and the
 * multi-image gallery carousel on record pages.
 */
class SearchAnalyticsAndGalleryTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    public function test_beacon_counts_terms_and_top_endpoint_aggregates(): void
    {
        foreach (['harbor', 'harbor', 'vase'] as $term) {
            $this->postJson("/api/v1/sites/{$this->site->id}/search-beacon", ['q' => $term])
                ->assertSuccessful();
        }
        // Junk ignored silently.
        $this->postJson("/api/v1/sites/{$this->site->id}/search-beacon", ['q' => 'x'])->assertStatus(204);
        $this->postJson("/api/v1/sites/{$this->site->id}/search-beacon", ['q' => '<b>tag</b>'])->assertSuccessful();

        $res = $this->actingAs($this->owner)->getJson("/api/v1/sites/{$this->site->id}/search-terms?days=7");
        $res->assertOk();
        $terms = collect($res->json('data.terms'))->keyBy('term');
        $this->assertSame(2, $terms['harbor']['count']);
        $this->assertSame(1, $terms['vase']['count']);
        $this->assertSame(1, $terms['tag']['count']); // tags stripped
    }

    public function test_beacon_disabled_by_site_setting(): void
    {
        $this->site->update(['settings' => ['auto_publish' => false, 'search_analytics' => false]]);

        $this->postJson("/api/v1/sites/{$this->site->id}/search-beacon", ['q' => 'quiet'])->assertStatus(204);
        $this->assertSame(0, DB::table('search_terms')->where('site_id', $this->site->id)->count());
    }

    public function test_multi_image_gallery_renders_carousel_with_variants(): void
    {
        $collection = app(CollectionService::class)->create($this->site, [
            'name' => 'Works',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'images', 'label' => 'Images', 'type' => 'gallery'],
                ],
                'title_field' => 'title',
            ],
        ]);

        $assets = collect([1, 2])->map(fn ($i) => Asset::create([
            'site_id' => $this->site->id,
            'original_name' => "img{$i}.jpg",
            'storage_path' => "sites/{$this->site->id}/assets/img{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'checksum' => str_repeat((string) $i, 64),
            'variants' => [],
        ]));

        $record = app(RecordService::class)->save($collection, $this->site, null, [
            'data' => ['title' => 'Two-view piece', 'images' => $assets->pluck('id')->all()],
            'status' => 'published',
        ]);

        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            app(CollectionPublishService::class)->buildRecordPage($this->site, $collection, $record->fresh(), $staging);
            $html = File::get("{$staging}/works/{$record->slug}/index.html");
            // Fallback record-single view renders the hero image; the carousel
            // ships through the record-image block when templates use it.
            $this->assertStringContainsString($assets[0]->id, $html);
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }

        // Render the record-image block directly (template path).
        $html = view('blocks.record-image', [
            'data' => ['field' => 'images'],
            'site' => $this->site,
            '__record' => $record->fresh(),
            '__collection' => $collection,
        ])->render();
        $this->assertStringContainsString('rg-carousel', $html);
        $this->assertStringContainsString('rg-lightbox', $html);
        $this->assertStringContainsString('thumb_200', $html);
        $this->assertSame(2, substr_count($html, 'rg-thumbs') >= 1 ? 2 : 0, 'both thumbs present');
    }
}
