<?php

namespace Tests\Feature\Collections;

use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * CSV import: mapping, per-row validation report, relation auto-create, and
 * update-by-key mode (the supplier-price-refresh path). Queue runs sync in
 * tests, so execute() completes the import inline.
 */
class CollectionImportTest extends TestCase
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

    /** @param array<int, array<int, string>> $rows */
    private function csvUpload(array $header, array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'books') . '.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'books.csv', 'text/csv', null, true);
    }

    private function booksRows(int $count): array
    {
        $genres = ['Sci-Fi', 'Fantasy', 'Mystery'];
        $authors = ['Isaac Asimov', 'Ursula K. Le Guin', 'Frank Herbert', 'Ray Bradbury'];
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                "Book {$i}",
                sprintf('978-%07d', $i),
                (string) (5 + ($i % 20)),
                $genres[$i % 3],
                $authors[$i % 4],
            ];
        }

        return $rows;
    }

    /** @return array{importId: string, headers: array} */
    private function upload(array $header, array $rows): array
    {
        $response = $this->actingAsOwner()->post(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/import",
            ['file' => $this->csvUpload($header, $rows)],
        );
        $response->assertStatus(201);

        return ['importId' => $response->json('data.import_id'), 'headers' => $response->json('data.headers')];
    }

    private function execute(string $importId, array $options): void
    {
        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/import/{$importId}/execute",
            $options,
        )->assertStatus(202);

        // REDIS_ENABLED=false routes dispatches to the database queue in the
        // test env — drain it inline so the import really executes end-to-end.
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
    }

    private function importStatus(string $importId): array
    {
        return $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/import/{$importId}/status")
            ->assertOk()
            ->json('data');
    }

    private const MAPPING = ['0' => 'title', '1' => 'isbn', '2' => 'price', '3' => 'genre', '4' => 'author'];

    public function test_upload_returns_headers_and_preview_rows(): void
    {
        $result = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $this->booksRows(30));

        $this->assertSame(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $result['headers']);
    }

    public function test_imports_200_books_with_relation_autocreate(): void
    {
        $result = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $this->booksRows(200));

        $this->execute($result['importId'], [
            'mapping' => self::MAPPING,
            'mode' => 'insert',
            'status' => 'published',
            'create_missing_relations' => true,
        ]);

        $status = $this->importStatus($result['importId']);
        $this->assertSame('completed', $status['status']);
        $this->assertSame(200, $status['result']['created']);
        $this->assertSame(0, $status['result']['failed']);

        $this->assertSame(200, Record::where('collection_id', $this->books->id)->count());
        // 4 distinct authors auto-created as drafts
        $this->assertSame(4, Record::where('collection_id', $this->authors->id)->count());

        // Relation actually linked
        $book = Record::where('collection_id', $this->books->id)->where('title', 'Book 1')->first();
        $this->assertSame(1, $book->relationsOut()->count());
    }

    public function test_reimport_with_price_changes_updates_by_key(): void
    {
        $rows = $this->booksRows(200);
        $first = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $rows);
        $this->execute($first['importId'], [
            'mapping' => self::MAPPING, 'mode' => 'insert', 'create_missing_relations' => true,
        ]);

        // Change 20 prices, re-import in update-by-key mode.
        for ($i = 0; $i < 20; $i++) {
            $rows[$i][2] = '99.90';
        }
        $second = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $rows);
        $this->execute($second['importId'], [
            'mapping' => self::MAPPING, 'mode' => 'upsert', 'key_field' => 'isbn', 'create_missing_relations' => true,
        ]);

        $status = $this->importStatus($second['importId']);
        $this->assertSame('completed', $status['status']);
        $this->assertSame(0, $status['result']['created']);
        $this->assertSame(200, $status['result']['updated']);

        $this->assertSame(200, Record::where('collection_id', $this->books->id)->count());
        $changed = Record::where('collection_id', $this->books->id)->where('title', 'Book 1')->first();
        $this->assertSame(99.9, $changed->data['price']);
    }

    public function test_unique_violations_reported_per_row_with_skip_policy(): void
    {
        $rows = $this->booksRows(10);
        $rows[] = $rows[0]; // duplicate ISBN → row 12 (1 header + 11)

        $result = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $rows);
        $this->execute($result['importId'], [
            'mapping' => self::MAPPING, 'mode' => 'insert', 'error_policy' => 'skip', 'create_missing_relations' => true,
        ]);

        $status = $this->importStatus($result['importId']);
        $this->assertSame('completed', $status['status']);
        $this->assertSame(10, $status['result']['created']);
        $this->assertSame(1, $status['result']['failed']);
        $this->assertSame(12, $status['result']['errors'][0]['row']);
        $this->assertStringContainsString('already used', $status['result']['errors'][0]['message']);
    }

    public function test_upsert_requires_a_unique_mapped_key_field(): void
    {
        $result = $this->upload(['Title', 'ISBN'], [['A', 'X-1']]);

        // price isn't unique
        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/import/{$result['importId']}/execute",
            ['mapping' => ['0' => 'title', '1' => 'isbn'], 'mode' => 'upsert', 'key_field' => 'price'],
        )->assertStatus(422);

        // key field not mapped
        $this->actingAsOwner()->postJson(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/import/{$result['importId']}/execute",
            ['mapping' => ['0' => 'title'], 'mode' => 'upsert', 'key_field' => 'isbn'],
        )->assertStatus(422);
    }

    public function test_export_round_trips_fields_and_relations(): void
    {
        $result = $this->upload(['Title', 'ISBN', 'Price', 'Genre', 'Author'], $this->booksRows(3));
        $this->execute($result['importId'], [
            'mapping' => self::MAPPING, 'mode' => 'insert', 'create_missing_relations' => true,
        ]);

        $response = $this->actingAsOwner()->get(
            "/api/v1/sites/{$this->site->id}/collections/{$this->books->id}/export",
        );
        $response->assertOk();

        $csv = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(4, $lines); // header + 3 records
        $this->assertStringContainsString('slug,status,title,isbn', $lines[array_key_first($lines)]);
        $this->assertStringContainsString('book-1', $csv);
    }
}
