<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Schema searchable-flag edits change what belongs in the tsvector without
 * touching any record — CollectionService must queue a reindex (the gap that
 * made 'search by author' fail on records seeded before the flag flip).
 */
class ReindexSearchTextTest extends TestCase
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

    private function tsMatches(string $term): int
    {
        return Record::where('collection_id', $this->books->id)
            ->whereRaw("search_text @@ plainto_tsquery('simple', ?)", [$term])
            ->count();
    }

    private function toggleSummarySearchable(bool $on): void
    {
        $schema = $this->books->schema;
        foreach ($schema['fields'] as &$field) {
            if ($field['key'] === 'summary') {
                $field['searchable'] = $on;
            }
        }
        app(CollectionService::class)->update($this->books, $this->site, [
            'name' => $this->books->name, 'schema' => $schema,
        ]);
        $this->books->refresh();
        Artisan::call('queue:work', ['--stop-when-empty' => true]); // drain ReindexCollectionJob
    }

    public function test_searchable_flag_change_reindexes_existing_records(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('tsvector is pgsql-only');
        }

        app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'published',
            'data' => ['title' => 'Dune', 'isbn' => 'RX-1', 'summary' => '<p>Spice and sandworms</p>'],
        ]);

        $this->assertSame(1, $this->tsMatches('sandworms'));

        $this->toggleSummarySearchable(false);
        $this->assertSame(0, $this->tsMatches('sandworms'));

        $this->toggleSummarySearchable(true);
        $this->assertSame(1, $this->tsMatches('sandworms'));

        // Title always indexed regardless of flags.
        $this->assertSame(1, $this->tsMatches('dune'));
    }

    public function test_reindex_command_rebuilds_a_collection(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('tsvector is pgsql-only');
        }

        app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'published',
            'data' => ['title' => 'Hyperion', 'isbn' => 'RX-2', 'summary' => '<p>The Shrike waits</p>'],
        ]);

        // Wipe the vector to simulate stale/missing state.
        DB::update('UPDATE records SET search_text = NULL WHERE collection_id = ?', [$this->books->id]);
        $this->assertSame(0, $this->tsMatches('shrike'));

        Artisan::call('collections:reindex', ['--site' => $this->site->id, '--collection' => 'books']);

        $this->assertSame(1, $this->tsMatches('shrike'));
    }
}
