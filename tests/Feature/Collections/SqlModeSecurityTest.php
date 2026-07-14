<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Queries\ScopedViewManager;
use App\Domain\Collections\Queries\SqlGuardException;
use App\Domain\Collections\Queries\SqlQueryGuard;
use App\Domain\Collections\Queries\SqlQueryRunner;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Track G-Q2 — the escape-attempt suite, written FIRST per spec: every fixture
 * here must be rejected (guard layer) or contained (role/RLS layer). Covers
 * DML/DDL attempts, statement chaining, system-catalog probing, function
 * abuse, real-table access, cross-tenant reads, and timeout enforcement.
 */
class SqlModeSecurityTest extends TestCase
{
    private Site $site;
    private ContentCollection $books;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);

        $this->books = app(CollectionService::class)->create($this->site, [
            'name' => 'Books',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                    ['key' => 'price', 'label' => 'Price', 'type' => 'price'],
                ],
                'title_field' => 'title',
            ],
        ]);
        app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'published', 'data' => ['title' => 'Dune', 'price' => 9.99],
        ]);

        app(ScopedViewManager::class)->rebuildSite($this->site);
    }

    private function guard(): SqlQueryGuard
    {
        return app(SqlQueryGuard::class);
    }

    private function assertRejected(string $sql, string $because = ''): void
    {
        try {
            $this->guard()->validate($sql, app(ScopedViewManager::class)->viewNames($this->site));
            $this->fail("Guard accepted forbidden SQL ({$because}): {$sql}");
        } catch (SqlGuardException) {
            $this->addToAssertionCount(1);
        }
    }

    // ── Guard layer: parse-time rejection ────────────────────────────────

    public function test_dml_ddl_dcl_rejected(): void
    {
        $this->assertRejected('DELETE FROM col_books', 'DML');
        $this->assertRejected('UPDATE col_books SET record_title = \'x\'', 'DML');
        $this->assertRejected('INSERT INTO col_books VALUES (1)', 'DML');
        $this->assertRejected('DROP TABLE records', 'DDL');
        $this->assertRejected('CREATE TABLE evil (id int)', 'DDL');
        $this->assertRejected('ALTER TABLE records DISABLE ROW LEVEL SECURITY', 'DDL');
        $this->assertRejected('GRANT ALL ON records TO PUBLIC', 'DCL');
        $this->assertRejected('TRUNCATE records', 'DDL');
        $this->assertRejected('SELECT record_title INTO evil FROM col_books', 'SELECT INTO');
        $this->assertRejected('SELECT * FROM col_books FOR UPDATE', 'row lock');
        $this->assertRejected("WITH w AS (DELETE FROM records RETURNING id) SELECT * FROM w", 'data-modifying CTE');
    }

    public function test_statement_chaining_rejected(): void
    {
        $this->assertRejected("SELECT 1; DELETE FROM records", 'semicolon chain');
        $this->assertRejected("SELECT 1; SELECT 2", 'two statements');
        // A single trailing semicolon is fine:
        $this->guard()->validate('SELECT record_title FROM col_books;', app(ScopedViewManager::class)->viewNames($this->site));
        $this->addToAssertionCount(1);
    }

    public function test_the_acceptance_fixture_select_with_appended_delete_rejected_at_parse(): void
    {
        // Spec acceptance: "the same query pasted with a DELETE appended is rejected at parse"
        $good = 'SELECT record_title, price FROM col_books WHERE price < 50';
        $this->guard()->validate($good, app(ScopedViewManager::class)->viewNames($this->site));
        $this->addToAssertionCount(1);

        $this->assertRejected($good . '; DELETE FROM records', 'appended DELETE');
    }

    public function test_system_catalog_probing_rejected(): void
    {
        $this->assertRejected('SELECT * FROM pg_catalog.pg_tables', 'pg_catalog');
        $this->assertRejected('SELECT * FROM pg_roles', 'pg_roles');
        $this->assertRejected('SELECT * FROM information_schema.tables', 'information_schema');
        $this->assertRejected('SELECT current_setting(\'app.current_tenant_id\')', 'GUC probing');
        $this->assertRejected('SELECT pg_read_file(\'/etc/passwd\')', 'file read');
    }

    public function test_real_tables_and_cross_schema_rejected(): void
    {
        $this->assertRejected('SELECT * FROM records', 'base table');
        $this->assertRejected('SELECT * FROM users', 'users table');
        $this->assertRejected('SELECT * FROM sites', 'sites table');
        $this->assertRejected('SELECT * FROM saved_queries', 'saved_queries table');
        $this->assertRejected('SELECT b.record_title, r.data FROM col_books b, records r', 'comma-join to base table');
        $this->assertRejected('SELECT * FROM cq_deadbeefdeadbeefdeadbeefdeadbeef.col_books', 'cross-schema qualified');
    }

    public function test_function_abuse_rejected(): void
    {
        $this->assertRejected('SELECT pg_sleep(60)', 'sleep DoS');
        $this->assertRejected('SELECT lo_import(\'/etc/passwd\')', 'large object');
        $this->assertRejected('SELECT query_to_xml(\'SELECT * FROM users\', true, true, \'\')', 'query_to_xml');
        $this->assertRejected('SELECT dblink(\'host=evil\', \'SELECT 1\')', 'dblink');
        $this->assertRejected("SELECT set_config('app.current_tenant_id', 'other', false)", 'GUC rewrite');
    }

    public function test_quoting_tricks_rejected(): void
    {
        $this->assertRejected('SELECT * FROM "records"', 'double-quoted identifier');
        $this->assertRejected('DO $$ BEGIN DELETE FROM records; END $$', 'dollar quoting');
        $this->assertRejected("SELECT E'\\x44ELETE'", 'escape-string literal');
        $this->assertRejected('EXPLAIN ANALYZE SELECT * FROM col_books', 'EXPLAIN ANALYZE executes');
    }

    public function test_unknown_view_rejected_but_own_views_allowed(): void
    {
        $this->assertRejected('SELECT * FROM col_nonexistent', 'unknown view');

        $views = app(ScopedViewManager::class)->viewNames($this->site);
        $this->guard()->validate('SELECT record_title, price FROM col_books ORDER BY price DESC', $views);
        $this->guard()->validate('EXPLAIN SELECT count(*) FROM col_books', $views);
        $this->addToAssertionCount(2);
    }

    public function test_legit_functions_with_inner_from_and_ctes_allowed(): void
    {
        $views = app(ScopedViewManager::class)->viewNames($this->site);

        // Functions whose grammar uses FROM inside the arg list must not trip
        // the FROM-relation check.
        $this->guard()->validate("SELECT extract(year FROM record_published_at) AS y FROM col_books", $views);
        $this->guard()->validate("SELECT substring(record_title FROM 1 FOR 3) FROM col_books", $views);
        // A read-only CTE (no data-modifying keyword) is fine.
        $this->guard()->validate("WITH cheap AS (SELECT record_title FROM col_books WHERE price < 10) SELECT * FROM cheap", $views);
        $this->addToAssertionCount(3);
    }

    // ── Runtime layer: restricted role + RLS backstop + timeout ─────────

    private function runnerAvailable(): bool
    {
        return app(SqlQueryRunner::class)->roleAvailable();
    }

    public function test_execution_returns_rows_under_restricted_role(): void
    {
        if (!$this->runnerAvailable()) {
            $this->markTestSkipped('cms_sql_guest role missing — run: CREATE ROLE cms_sql_guest NOLOGIN; GRANT cms_sql_guest TO cms_saas;');
        }

        $result = app(SqlQueryRunner::class)->run($this->site, 'SELECT record_title, price FROM col_books ORDER BY price');
        $this->assertSame('table', $result['type']);
        $this->assertSame('Dune', $result['rows'][0]['record_title']);
        $this->assertEquals(9.99, $result['rows'][0]['price']);
    }

    public function test_cross_tenant_views_yield_nothing_even_if_guard_bypassed(): void
    {
        if (!$this->runnerAvailable()) {
            $this->markTestSkipped('cms_sql_guest role missing');
        }

        // Second tenant with its own site + collection + view schema.
        $otherTenant = Tenant::factory()->create();
        $otherOwner = User::factory()->owner()->create(['tenant_id' => $otherTenant->id]);
        $this->setTenantScope($otherOwner);
        $otherSite = Site::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherBooks = app(CollectionService::class)->create($otherSite, [
            'name' => 'Books',
            'schema' => ['fields' => [['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true]], 'title_field' => 'title'],
        ]);
        app(RecordService::class)->save($otherBooks, $otherSite, null, ['status' => 'published', 'data' => ['title' => 'Secret Book']]);
        app(ScopedViewManager::class)->rebuildSite($otherSite);

        // Back to tenant A's GUC; execute raw SQL naming tenant B's schema,
        // simulating a guard bypass — security_invoker + RLS must yield zero rows.
        $this->setTenantScope($this->owner);
        $schemaB = app(ScopedViewManager::class)->schemaName($otherSite);

        $rows = app(SqlQueryRunner::class)->runUnguardedForTests($this->site, "SELECT * FROM {$schemaB}.col_books");
        $this->assertSame([], $rows, 'cross-tenant view must be empty under tenant A GUC');
    }

    public function test_statement_timeout_enforced(): void
    {
        if (!$this->runnerAvailable()) {
            $this->markTestSkipped('cms_sql_guest role missing');
        }

        config(['collections.sql_timeout_ms' => 300, 'collections.sql_cost_limit' => 1e12]);

        $pathological = 'SELECT count(*) FROM col_books a, col_books b, col_books c, col_books d, col_books e,'
            . ' col_books f, col_books g, col_books h, col_books i, col_books j, col_books k, col_books l';
        // 1 row per view → tiny... make it heavy via generate_series? Not whitelisted.
        // Seed enough rows for a genuinely slow cross join instead.
        $rs = app(RecordService::class);
        for ($i = 0; $i < 60; $i++) {
            $rs->save($this->books, $this->site, null, ['status' => 'published', 'data' => ['title' => "B{$i}", 'price' => $i]]);
        }

        try {
            app(SqlQueryRunner::class)->run($this->site, $pathological);
            $this->fail('pathological cross join should hit the statement timeout');
        } catch (SqlGuardException $e) {
            $this->assertStringContainsString('too long', $e->getMessage());
        }
    }

    public function test_row_cap_auto_limit_injected(): void
    {
        if (!$this->runnerAvailable()) {
            $this->markTestSkipped('cms_sql_guest role missing');
        }

        config(['collections.sql_row_cap' => 5]);
        $rs = app(RecordService::class);
        for ($i = 0; $i < 10; $i++) {
            $rs->save($this->books, $this->site, null, ['status' => 'published', 'data' => ['title' => "C{$i}", 'price' => $i]]);
        }

        $result = app(SqlQueryRunner::class)->run($this->site, 'SELECT record_title FROM col_books');
        $this->assertCount(5, $result['rows']);
        $this->assertTrue($result['capped']);
    }

    public function test_draft_records_invisible_through_views(): void
    {
        if (!$this->runnerAvailable()) {
            $this->markTestSkipped('cms_sql_guest role missing');
        }

        app(RecordService::class)->save($this->books, $this->site, null, [
            'status' => 'draft', 'data' => ['title' => 'Unpublished Draft', 'price' => 1],
        ]);

        $result = app(SqlQueryRunner::class)->run($this->site, 'SELECT record_title FROM col_books');
        $this->assertNotContains('Unpublished Draft', array_column($result['rows'], 'record_title'));
    }
}
