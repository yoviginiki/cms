<?php

namespace App\Domain\Collections\Queries;

use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Track G-Q2 — per-site scoped views for Advanced SQL mode. Each site gets a
 * dedicated schema `cq_<siteid-hex>` holding one readable view per collection
 * (`col_<slug>`, JSONB fields exposed as typed columns) plus a relation view
 * per relation field (`rel_<slug>_<key>`, pivot fields as typed columns).
 *
 * Security model (three walls, defense in depth):
 *  1. Views are owned by the app role (cms_saas) and body-filter to this
 *     site's PUBLISHED records — the SQL guest never names a base table.
 *  2. Default (non-security_invoker) views + FORCE RLS on records/sites mean
 *     underlying access re-evaluates the tenant GUC as the owner — a
 *     cross-tenant schema reference yields zero rows even if the guard is
 *     bypassed.
 *  3. cms_sql_guest gets USAGE + SELECT on these views ONLY; no grant on
 *     public, records, sites, anything else.
 *
 * Rebuilt when a collection's schema changes (CollectionService) or via
 * `collections:rebuild-views`.
 */
class ScopedViewManager
{
    public function schemaName(Site $site): string
    {
        return 'cq_' . str_replace('-', '', $site->id);
    }

    public function collectionViewName(ContentCollection $collection): string
    {
        return 'col_' . str_replace('-', '_', $collection->slug);
    }

    public function relationViewName(ContentCollection $collection, string $relationKey): string
    {
        return 'rel_' . str_replace('-', '_', $collection->slug) . '_' . $relationKey;
    }

    /** @return array<int, string> every view name available on the site */
    public function viewNames(Site $site): array
    {
        $names = [];
        foreach (ContentCollection::where('site_id', $site->id)->get() as $collection) {
            $names[] = $this->collectionViewName($collection);
            foreach ($collection->fields() as $field) {
                if ($field['type'] === 'relation') {
                    $names[] = $this->relationViewName($collection, $field['key']);
                }
            }
        }

        return $names;
    }

    /** Drop + recreate the site's view schema. Idempotent. */
    public function rebuildSite(Site $site): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = $this->schemaName($site);

        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        DB::statement("CREATE SCHEMA {$schema}");

        foreach (ContentCollection::where('site_id', $site->id)->get() as $collection) {
            $this->createCollectionView($schema, $site, $collection);
            foreach ($collection->fields() as $field) {
                if ($field['type'] === 'relation') {
                    $this->createRelationView($schema, $site, $collection, $field);
                }
            }
        }

        $this->grantToGuest($schema);
    }

    private function createCollectionView(string $schema, Site $site, ContentCollection $collection): void
    {
        $view = $this->collectionViewName($collection);

        // System columns first; a field key colliding with one is suffixed.
        $used = ['id', 'record_title', 'record_slug', 'record_status', 'record_published_at'];
        $columns = [
            'r.id AS id',
            'r.title AS record_title',
            'r.slug AS record_slug',
            'r.status AS record_status',
            'r.published_at AS record_published_at',
        ];

        foreach ($collection->fields() as $field) {
            $expr = $this->fieldColumnExpr($field);
            if ($expr === null) {
                continue; // relation/gallery/rich_text excluded from the col view
            }
            $alias = $field['key'];
            if (in_array($alias, $used, true)) {
                $alias .= '_field';
            }
            $used[] = $alias;
            $columns[] = "{$expr} AS {$alias}";
        }

        $columnSql = implode(",\n  ", $columns);
        $siteId = $this->literal($site->id);
        $collectionId = $this->literal($collection->id);

        DB::statement("
            CREATE VIEW {$schema}.{$view} AS
            SELECT
              {$columnSql}
            FROM records r
            WHERE r.collection_id = {$collectionId}
              AND r.site_id = {$siteId}
              AND r.status = 'published'
        ");
    }

    private function createRelationView(string $schema, Site $site, ContentCollection $collection, array $field): void
    {
        $view = $this->relationViewName($collection, $field['key']);

        $columns = [
            'rr.from_record_id AS from_id',
            'fr.title AS from_title',
            'rr.to_record_id AS to_id',
            'tr.title AS to_title',
            'rr.position AS position',
        ];
        $used = ['from_id', 'from_title', 'to_id', 'to_title', 'position'];

        foreach ($field['relation']['pivot_fields'] ?? [] as $pivot) {
            $expr = $this->pivotColumnExpr($pivot);
            $alias = $pivot['key'];
            if (in_array($alias, $used, true)) {
                $alias .= '_field';
            }
            $used[] = $alias;
            $columns[] = "{$expr} AS {$alias}";
        }

        $columnSql = implode(",\n  ", $columns);
        $siteId = $this->literal($site->id);
        $collectionId = $this->literal($collection->id);
        $relationKey = $this->literal($field['key']);

        DB::statement("
            CREATE VIEW {$schema}.{$view} AS
            SELECT
              {$columnSql}
            FROM record_relations rr
            JOIN records fr ON fr.id = rr.from_record_id AND fr.status = 'published' AND fr.collection_id = {$collectionId}
            JOIN records tr ON tr.id = rr.to_record_id AND tr.status = 'published'
            WHERE rr.relation_key = {$relationKey}
              AND rr.site_id = {$siteId}
        ");
    }

    /** Typed column expression for a schema field, or null to exclude it. */
    private function fieldColumnExpr(array $field): ?string
    {
        $key = $field['key'];
        $text = "r.data->>'{$key}'";

        return match ($field['type']) {
            'text', 'sku', 'email', 'url', 'phone', 'select', 'image', 'file' => "({$text})::text",
            'number', 'price' => "NULLIF({$text}, '')::numeric",
            'boolean' => "({$text})::boolean",
            'date' => "NULLIF({$text}, '')::date",
            'multi_select' => "(r.data->'{$key}')",
            default => null, // relation, gallery, rich_text
        };
    }

    private function pivotColumnExpr(array $pivot): string
    {
        $key = $pivot['key'];
        $text = "rr.pivot->>'{$key}'";

        return match ($pivot['type']) {
            'number', 'price' => "NULLIF({$text}, '')::numeric",
            'boolean' => "({$text})::boolean",
            'date' => "NULLIF({$text}, '')::date",
            default => "({$text})::text",
        };
    }

    private function grantToGuest(string $schema): void
    {
        if (!$this->guestRoleExists()) {
            return; // role not provisioned yet — views exist but aren't queryable
        }

        DB::statement("GRANT USAGE ON SCHEMA {$schema} TO cms_sql_guest");
        DB::statement("GRANT SELECT ON ALL TABLES IN SCHEMA {$schema} TO cms_sql_guest");
    }

    public function guestRoleExists(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne("SELECT 1 AS ok FROM pg_roles WHERE rolname = 'cms_sql_guest'") !== null;
    }

    /** A pgsql string literal (ids/keys are already tightly constrained). */
    private function literal(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
