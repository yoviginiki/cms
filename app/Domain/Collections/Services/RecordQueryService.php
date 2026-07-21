<?php

namespace App\Domain\Collections\Services;

use App\Models\ContentCollection;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Track G3 — the read-side query engine behind the public collections API
 * (and later the G-Q saved-query compiler). Published records only; every
 * filter/sort key is validated against the collection schema before it may
 * appear in SQL; full-text runs on the tsvector maintained by RecordService
 * (searchable fields + SKUs + pivot part numbers + related titles).
 */
class RecordQueryService
{
    public const MAX_PER_PAGE = 50;
    private const MAX_FACET_VALUES = 50;

    /**
     * @param array{q?: string, facets?: array<string, array<int,string>>, sort?: string, direction?: string, per_page?: int, cursor?: ?string} $params
     * @return array{rows: \Illuminate\Contracts\Pagination\CursorPaginator, total: int}
     */
    public function search(ContentCollection $collection, array $params)
    {
        $query = $this->baseQuery($collection, $params);

        $this->applySort($query, $collection, $params);

        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($params['per_page'] ?? 20)));

        return [
            'rows' => $query->with('relationsOut.toRecord:id,title,slug,status')
                ->cursorPaginate($perPage, ['*'], 'cursor', $params['cursor'] ?? null),
            'total' => $this->baseQuery($collection, $params)->count(),
        ];
    }

    /**
     * Per-facet value counts, each computed under q + every OTHER facet
     * (the static island's semantics, server-side).
     *
     * @return array<string, array<string, int>>
     */
    public function facetCounts(ContentCollection $collection, array $params): array
    {
        $counts = [];

        foreach ($collection->fields() as $field) {
            if (!($field['facetable'] ?? false)) {
                continue;
            }
            $key = $field['key'];

            $others = $params;
            unset($others['facets'][$key]);
            $base = $this->baseQuery($collection, $others);

            $counts[$key] = match ($field['type']) {
                'select' => $this->scalarCounts($base, $key),
                'boolean' => $this->scalarCounts($base, $key),
                'multi_select' => $this->arrayCounts($base, $key),
                'relation' => $this->relationCounts($base, $key),
                default => [],
            };
        }

        return $counts;
    }

    private function baseQuery(ContentCollection $collection, array $params): Builder
    {
        $query = Record::query()
            ->where('collection_id', $collection->id)
            ->where('status', 'published');

        $q = trim((string) ($params['q'] ?? ''));
        if ($q !== '') {
            $tsquery = $this->buildTsQuery($q);
            if ($tsquery !== '') {
                $query->whereRaw("search_text @@ to_tsquery('simple', ?)", [$tsquery]);
            }
        }

        foreach (($params['facets'] ?? []) as $key => $values) {
            $field = $collection->field($key);
            $values = array_values(array_filter(array_map('strval', (array) $values), fn ($v) => $v !== ''));
            if (!$field || !($field['facetable'] ?? false) || $values === []) {
                continue;
            }
            $this->applyFacet($query, $field, $values);
        }

        return $query;
    }

    /** OR within a facet's selected values, AND across facets. */
    private function applyFacet(Builder $query, array $field, array $values): void
    {
        $key = $field['key'];

        if ($field['type'] === 'relation') {
            $query->whereExists(function ($sub) use ($key, $values) {
                $sub->select(DB::raw(1))
                    ->from('record_relations as rr')
                    ->join('records as tr', 'tr.id', '=', 'rr.to_record_id')
                    ->whereColumn('rr.from_record_id', 'records.id')
                    ->where('rr.relation_key', $key)
                    ->whereIn('tr.title', $values);
            });

            return;
        }

        $query->where(function ($w) use ($field, $key, $values) {
            foreach ($values as $value) {
                $typed = match ($field['type']) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'multi_select' => [$value], // jsonb array containment
                    default => $value,
                };
                $w->orWhereRaw('data @> ?', [json_encode([$key => $typed])]);
            }
        });
    }

    private function applySort(Builder $query, ContentCollection $collection, array $params): void
    {
        $direction = strtolower((string) ($params['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sort = (string) ($params['sort'] ?? '');

        if (in_array($sort, ['published_at', 'created_at', 'updated_at', 'title', 'position'], true)) {
            $query->orderBy($sort, $direction);
        } elseif ($sort !== '' && ($field = $collection->field($sort)) && !in_array($field['type'], ['relation', 'gallery', 'rich_text', 'image', 'file', 'computed'], true)) {
            $accessor = in_array($field['type'], ['number', 'price'], true) ? "data->'{$field['key']}'" : "data->>'{$field['key']}'";
            $query->orderByRaw("{$accessor} {$direction} NULLS LAST");
        } else {
            $query->orderByDesc('published_at');
        }

        // Cursor pagination needs a total order.
        $query->orderBy('id');
    }

    /**
     * "compressor acme" → 'compressor' & 'acme':* — every term must match,
     * the final term matches as a prefix (type-ahead over part numbers).
     * Falls back to plain match when sanitization empties the input.
     */
    public function buildTsQuery(string $q): string
    {
        $terms = preg_split('/\s+/u', mb_strtolower(trim(mb_substr($q, 0, 200)))) ?: [];
        $lexemes = [];
        foreach ($terms as $term) {
            // Strip tsquery syntax; keep letters/digits/dash/dot/slash (part numbers).
            $term = preg_replace('/[^\p{L}\p{N}\-.\/]+/u', '', $term);
            if ($term !== '' && $term !== '-') {
                $lexemes[] = "'" . str_replace("'", "''", $term) . "'";
            }
            if (count($lexemes) >= 8) {
                break; // hard cap on query complexity
            }
        }
        if ($lexemes === []) {
            return '';
        }
        $lexemes[count($lexemes) - 1] .= ':*';

        return implode(' & ', $lexemes);
    }

    /** @return array<string, int> */
    private function scalarCounts(Builder $base, string $key): array
    {
        return $base->clone()
            ->selectRaw("data->>'{$key}' as v, count(*) as n")
            // jsonb_exists() instead of the `?` operator — PDO would eat `?` as a placeholder
            ->whereRaw("jsonb_exists(data, '{$key}')")
            ->groupBy('v')
            ->orderByDesc('n')
            ->limit(self::MAX_FACET_VALUES)
            ->pluck('n', 'v')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** @return array<string, int> */
    private function arrayCounts(Builder $base, string $key): array
    {
        $sub = $base->clone()->select('data');

        $rows = DB::table(DB::raw("({$sub->toSql()}) as filtered"))
            ->mergeBindings($sub->getQuery())
            ->selectRaw("jsonb_array_elements_text(filtered.data->'{$key}') as v, count(*) as n")
            ->whereRaw("jsonb_typeof(filtered.data->'{$key}') = 'array'")
            ->groupBy('v')
            ->orderByDesc('n')
            ->limit(self::MAX_FACET_VALUES)
            ->pluck('n', 'v');

        return $rows->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<string, int> */
    private function relationCounts(Builder $base, string $key): array
    {
        $ids = $base->clone()->select('id');

        return DB::table('record_relations as rr')
            ->join('records as tr', 'tr.id', '=', 'rr.to_record_id')
            ->whereIn('rr.from_record_id', $ids)
            ->where('rr.relation_key', $key)
            ->selectRaw('tr.title as v, count(*) as n')
            ->groupBy('tr.title')
            ->orderByDesc('n')
            ->limit(self::MAX_FACET_VALUES)
            ->pluck('n', 'v')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
