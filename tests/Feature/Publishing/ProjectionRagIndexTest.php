<?php

namespace Tests\Feature\Publishing;

use App\Domain\Projection\Rag\RagStore;
use App\Models\Block;
use App\Models\Page;
use App\Models\RagChunkRecord;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProjectionRagIndexTest extends TestCase
{
    private function sitePage(): array
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'guide']);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'rich-text', 'order' => 0,
            'data' => ['content' => '<p>The projection is the single source of truth.</p>'],
        ]);

        return [$site->fresh(), $page];
    }

    public function test_index_command_stores_chunks_and_is_incremental(): void
    {
        [$site] = $this->sitePage();

        Artisan::call('projection:rag:index', ['site' => $site->slug]);
        $chunks = RagChunkRecord::where('site_id', $site->id)->get();
        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('single source of truth', $chunks->first()->text);
        $this->assertNotEmpty($chunks->first()->embedding);

        // Re-index: no content changed → nothing new written.
        Artisan::call('projection:rag:index', ['site' => $site->slug]);
        $this->assertCount(1, RagChunkRecord::where('site_id', $site->id)->get());
    }

    public function test_search_ranks_by_cosine_similarity(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);

        $mk = function (string $id, array $emb) use ($site) {
            RagChunkRecord::create([
                'site_id' => $site->id, 'segment_id' => $id, 'address' => "{$id}#content",
                'heading_path' => [], 'text' => "text {$id}", 'hash' => hash('sha256', $id),
                'embedding' => $emb, 'model' => 'test',
            ]);
        };
        $mk('near', [1.0, 0.0, 0.0]);
        $mk('mid', [0.8, 0.2, 0.0]);
        $mk('far', [0.0, 1.0, 0.0]);

        $results = app(RagStore::class)->search($site->id, [1.0, 0.0, 0.0], 2);

        $this->assertCount(2, $results);
        $this->assertSame('near', $results[0]['chunk']->segment_id);
        $this->assertSame('mid', $results[1]['chunk']->segment_id);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 1e-9);
    }
}
