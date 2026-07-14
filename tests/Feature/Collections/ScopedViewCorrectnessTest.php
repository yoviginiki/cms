<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Queries\ScopedViewManager;
use App\Domain\Collections\Queries\SimpleToSql;
use App\Domain\Collections\Services\RecordService;
use App\Domain\Collections\Services\CollectionService;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Track G-Q2 — verifies the scoped-view PROJECTION independently of the
 * restricted role: typed columns, published-only, site-scoped, pivot columns,
 * and the Show-as-SQL bridge running identically against the views. Queries
 * the views directly (owner connection), so it runs even before cms_sql_guest
 * is provisioned — covering the data-shaping wall the role sits on top of.
 */
class ScopedViewCorrectnessTest extends TestCase
{
    use BuildsCollections;

    private Site $site;
    private ContentCollection $suppliers;
    private ContentCollection $parts;
    private string $schema;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('scoped views are pgsql-only');
        }
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);

        [$this->suppliers, $this->parts] = $this->createPartsAndSuppliers($this->site);

        $rs = app(RecordService::class);
        $acme = $rs->save($this->suppliers, $this->site, null, ['status' => 'published', 'data' => ['name' => 'Acme', 'lead_time' => 4]]);
        $rs->save($this->parts, $this->site, null, [
            'status' => 'published',
            'data' => ['name' => 'Compressor', 'part_number' => 'cmp-100'],
            'relations' => ['suppliers' => [['id' => $acme->id, 'pivot' => ['supplier_part_number' => 'acme-x1', 'supplier_price' => 19.9]]]],
        ]);
        $rs->save($this->parts, $this->site, null, ['status' => 'draft', 'data' => ['name' => 'Draft Part', 'part_number' => 'drf-1']]);

        app(ScopedViewManager::class)->rebuildSite($this->site);
        $this->schema = app(ScopedViewManager::class)->schemaName($this->site);
    }

    public function test_collection_view_exposes_typed_columns_published_only(): void
    {
        $rows = DB::select("SELECT record_title, name, part_number FROM {$this->schema}.col_parts ORDER BY record_title");

        // Draft excluded.
        $this->assertCount(1, $rows);
        $this->assertSame('Compressor', $rows[0]->record_title);
        $this->assertSame('CMP-100', $rows[0]->part_number); // SKU normalized uppercase on save

        // Column is genuinely typed (numeric price sorts numerically, text stays text).
        $type = DB::selectOne("
            SELECT data_type FROM information_schema.columns
            WHERE table_schema = ? AND table_name = 'col_parts' AND column_name = 'part_number'
        ", [$this->schema]);
        $this->assertSame('text', $type->data_type);
    }

    public function test_relation_view_exposes_pivot_columns(): void
    {
        $row = DB::selectOne("SELECT from_title, to_title, supplier_part_number, supplier_price FROM {$this->schema}.rel_parts_suppliers");

        $this->assertSame('Compressor', $row->from_title);
        $this->assertSame('Acme', $row->to_title);
        $this->assertSame('ACME-X1', $row->supplier_part_number); // SKU normalized uppercase
        $this->assertEquals(19.9, $row->supplier_price);
    }

    public function test_views_are_site_scoped(): void
    {
        // A second site's identical collection must not appear in this site's view.
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherParts = app(CollectionService::class)->create($other, [
            'name' => 'Parts',
            'schema' => ['fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'part_number', 'label' => 'PN', 'type' => 'sku'],
            ], 'title_field' => 'name'],
        ]);
        app(RecordService::class)->save($otherParts, $other, null, ['status' => 'published', 'data' => ['name' => 'Other Part', 'part_number' => 'oth-1']]);
        app(ScopedViewManager::class)->rebuildSite($other);

        $titles = array_column(DB::select("SELECT record_title FROM {$this->schema}.col_parts"), 'record_title');
        $this->assertNotContains('Other Part', $titles);
    }

    public function test_show_as_sql_runs_identically_to_simple_mode(): void
    {
        $definition = app(\App\Domain\Collections\Queries\SavedQueryValidator::class)->validate([
            'collection_id' => $this->parts->id,
            'filters' => ['op' => 'and', 'children' => [['field' => 'name', 'operator' => 'contains', 'value' => 'ompress']]],
            'sort' => [['field' => 'title', 'direction' => 'asc']],
        ], $this->site);

        // Simple-mode result
        $simple = app(\App\Domain\Collections\Queries\SimpleQueryCompiler::class)->run($this->parts, $definition);
        $simpleTitles = $simple['rows']->pluck('title')->all();

        // The bridge SQL, run directly against the views (schema qualified)
        $sql = app(SimpleToSql::class)->render($definition, $this->parts);
        $this->assertStringContainsString('FROM col_parts', $sql);
        $qualified = str_replace(['FROM col_parts', 'FROM rel_parts_suppliers'], ["FROM {$this->schema}.col_parts", "FROM {$this->schema}.rel_parts_suppliers"], $sql);
        $bridgeTitles = array_column(DB::select($qualified), 'record_title');

        $this->assertSame($simpleTitles, $bridgeTitles);
        $this->assertSame(['Compressor'], $bridgeTitles);
    }

    public function test_relation_hop_bridge_sql_matches(): void
    {
        // Depth-2 target-field hop (the spec's supplier.lead_time example).
        $definition = app(\App\Domain\Collections\Queries\SavedQueryValidator::class)->validate([
            'collection_id' => $this->parts->id,
            'filters' => ['op' => 'and', 'children' => [['field' => 'suppliers.lead_time', 'operator' => 'lt', 'value' => 10]]],
        ], $this->site);

        // Simple-mode result (Acme lead_time 4 < 10 → Compressor).
        $simple = app(\App\Domain\Collections\Queries\SimpleQueryCompiler::class)->run($this->parts, $definition);
        $this->assertSame(['Compressor'], $simple['rows']->pluck('title')->all());

        $sql = app(SimpleToSql::class)->render($definition, $this->parts);
        $this->assertStringContainsString('EXISTS', $sql);
        $qualified = str_replace(
            ['FROM col_parts', 'FROM rel_parts_suppliers', 'JOIN col_suppliers'],
            ["FROM {$this->schema}.col_parts", "FROM {$this->schema}.rel_parts_suppliers", "JOIN {$this->schema}.col_suppliers"],
            $sql,
        );
        $titles = array_column(DB::select($qualified), 'record_title');
        $this->assertSame(['Compressor'], $titles);
    }
}
