<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionPublishService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\SavedQuery;
use App\Models\Site;
use App\Support\Blocks\RecordDisplay;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collections v3 — computed rollup fields (count/sum over incoming
 * relations) and static saved-query JSON feeds.
 */
class ComputedFieldsAndFeedsTest extends TestCase
{
    private Site $site;
    private ContentCollection $authors;
    private ContentCollection $books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);

        $service = app(CollectionService::class);
        $this->authors = $service->create($this->site, [
            'name' => 'Authors',
            'tier' => 'static',
            'schema' => [
                'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]],
                'title_field' => 'name',
            ],
        ]);
        $this->books = $service->create($this->site, [
            'name' => 'Books',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'pages', 'label' => 'Pages', 'type' => 'number'],
                    ['key' => 'author', 'label' => 'Author', 'type' => 'relation',
                        'relation' => ['collection_id' => $this->authors->id, 'mode' => 'one']],
                ],
                'title_field' => 'title',
            ],
        ]);
    }

    private function addComputedFields(): void
    {
        $schema = $this->authors->schema;
        $schema['fields'][] = [
            'key' => 'book_count', 'label' => 'Books', 'type' => 'computed',
            'computed' => ['fn' => 'count', 'collection_id' => $this->books->id, 'relation_key' => 'author'],
        ];
        $schema['fields'][] = [
            'key' => 'total_pages', 'label' => 'Total pages', 'type' => 'computed',
            'computed' => ['fn' => 'sum', 'collection_id' => $this->books->id, 'relation_key' => 'author', 'sum_field' => 'pages'],
        ];
        app(CollectionService::class)->update($this->authors, $this->site, [
            'name' => 'Authors', 'schema' => $schema,
        ]);
        $this->authors->refresh();
    }

    public function test_computed_count_and_sum_resolve_from_published_relations(): void
    {
        $this->addComputedFields();
        $records = app(RecordService::class);

        $author = $records->save($this->authors, $this->site, null, [
            'data' => ['name' => 'Ursula'], 'status' => 'published',
        ]);
        $records->save($this->books, $this->site, null, [
            'data' => ['title' => 'Book A', 'pages' => 100], 'status' => 'published',
            'relations' => ['author' => [['id' => $author->id]]],
        ]);
        $records->save($this->books, $this->site, null, [
            'data' => ['title' => 'Book B', 'pages' => 50], 'status' => 'published',
            'relations' => ['author' => [['id' => $author->id]]],
        ]);
        // Draft book must not count.
        $records->save($this->books, $this->site, null, [
            'data' => ['title' => 'Book C', 'pages' => 999], 'status' => 'draft',
            'relations' => ['author' => [['id' => $author->id]]],
        ]);

        $author->load('relationsOut');
        $this->assertSame('2', RecordDisplay::display($this->site, $this->authors->fresh(), $author, 'book_count'));
        $this->assertSame('150', RecordDisplay::display($this->site, $this->authors->fresh(), $author, 'total_pages'));
    }

    public function test_computed_config_validated(): void
    {
        $schema = $this->authors->schema;
        $schema['fields'][] = [
            'key' => 'bad', 'label' => 'Bad', 'type' => 'computed',
            'computed' => ['fn' => 'sum', 'collection_id' => $this->books->id, 'relation_key' => 'author', 'sum_field' => 'title'],
        ];
        $this->expectException(ValidationException::class);
        app(CollectionService::class)->update($this->authors, $this->site, ['name' => 'Authors', 'schema' => $schema]);
    }

    public function test_query_feed_published_as_static_json(): void
    {
        $records = app(RecordService::class);
        $records->save($this->books, $this->site, null, [
            'data' => ['title' => 'Feed me', 'pages' => 12], 'status' => 'published',
        ]);

        $query = SavedQuery::create([
            'site_id' => $this->site->id,
            'name' => 'All books',
            'slug' => 'all-books',
            'mode' => 'simple',
            'definition' => ['collection_id' => $this->books->id, 'where' => null, 'order' => null, 'limit' => 100],
            'settings' => ['feed_enabled' => true],
            'created_by' => $this->owner->id,
        ]);

        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            $warnings = app(CollectionPublishService::class)->buildQueryFeeds($this->site, $staging);
            $this->assertSame([], $warnings);

            $feed = json_decode(File::get("{$staging}/queries/all-books.json"), true);
            $this->assertSame('all-books', $feed['query']);
            $this->assertSame(1, $feed['count']);
            $this->assertSame('Feed me', $feed['rows'][0]['t']);
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }
    }

    public function test_disabled_feed_not_published(): void
    {
        SavedQuery::create([
            'site_id' => $this->site->id,
            'name' => 'Hidden',
            'slug' => 'hidden',
            'mode' => 'simple',
            'definition' => ['collection_id' => $this->books->id, 'where' => null, 'order' => null, 'limit' => 10],
            'settings' => [],
            'created_by' => $this->owner->id,
        ]);

        $staging = storage_path('app/test-builds/' . uniqid());
        File::ensureDirectoryExists($staging);
        try {
            app(CollectionPublishService::class)->buildQueryFeeds($this->site, $staging);
            $this->assertFalse(File::exists("{$staging}/queries/hidden.json"));
        } finally {
            File::deleteDirectory(storage_path('app/test-builds'));
        }
    }
}
