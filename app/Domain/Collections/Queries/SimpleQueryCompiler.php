<?php

namespace App\Domain\Collections\Queries;

use App\Models\ContentCollection;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Track G-Q — compiles a VALIDATED Simple-mode definition to Eloquent and
 * executes it. Every field key was schema-validated (safe charset) before it
 * may be interpolated; every value travels as a binding. Published records
 * only — this powers public output.
 *
 * Result-shape contract (both authoring modes):
 *  {type:'records', rows: Record[], total}                       — plain lists
 *  {type:'value',   value: number, label}                        — one metric, no grouping
 *  {type:'table',   columns:[{key,label}], rows:[{...}], total}  — grouped/multi-metric
 */
class SimpleQueryCompiler
{
    public function __construct(private SavedQueryValidator $validator)
    {
    }

    /**
     * @param array $definition validated definition (SavedQueryValidator output)
     * @param array<string, mixed> $params resolved public-param values
     */
    public function run(ContentCollection $collection, array $definition, array $params = []): array
    {
        $query = Record::query()
            ->where('records.collection_id', $collection->id)
            ->where('records.status', 'published');

        if (!empty($definition['filters'])) {
            $this->applyGroup($query, $collection, $definition['filters'], $params);
        }

        if (!empty($definition['aggregate'])) {
            return $this->runAggregate($query, $collection, $definition['aggregate']);
        }

        foreach ($definition['sort'] ?? [] as $sort) {
            $this->applySort($query, $collection, $sort['field'], $sort['direction']);
        }
        if (($definition['sort'] ?? []) === []) {
            $query->orderByDesc('records.published_at');
        }
        $query->orderBy('records.id');

        $total = (clone $query)->count();
        $rows = $query->with('relationsOut.toRecord:id,title,slug,status')
            ->limit($definition['limit'] ?? 100)
            ->get();

        return ['type' => 'records', 'rows' => $rows, 'total' => $total];
    }

    private function applyGroup(Builder $query, ContentCollection $collection, array $group, array $params): void
    {
        $method = $group['op'] === 'or' ? 'orWhere' : 'where';

        $query->where(function (Builder $sub) use ($collection, $group, $params, $method) {
            foreach ($group['children'] as $child) {
                if (isset($child['children'])) {
                    $sub->{$method}(function (Builder $inner) use ($collection, $child, $params) {
                        $this->applyGroup($inner, $collection, $child, $params);
                    });
                } else {
                    $sub->{$method}(function (Builder $inner) use ($collection, $child, $params) {
                        $this->applyCondition($inner, $collection, $child, $params);
                    });
                }
            }
        });
    }

    private function applyCondition(Builder $query, ContentCollection $collection, array $condition, array $params): void
    {
        $resolved = $this->validator->resolvePath($condition['field'], $collection, 'filters');
        $value = $this->resolveValue($condition['value'], $params);

        if ($resolved['relation'] === null) {
            $this->applyLocalCondition($query, 'records', $resolved['field'], $condition['operator'], $value);

            return;
        }

        // One relation hop: EXISTS over the edge into the target record.
        $relationKey = $resolved['relation']['key'];
        $query->whereExists(function ($sub) use ($relationKey, $resolved, $condition, $value) {
            $sub->select(DB::raw(1))
                ->from('record_relations as rr')
                ->join('records as tr', 'tr.id', '=', 'rr.to_record_id')
                ->whereColumn('rr.from_record_id', 'records.id')
                ->where('rr.relation_key', $relationKey)
                ->where('tr.status', 'published');
            $this->applyLocalCondition($sub, 'tr', $resolved['field'], $condition['operator'], $value, grammarless: true);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function applyLocalCondition($query, string $table, array $field, string $operator, mixed $value, bool $grammarless = false): void
    {
        $key = $field['key'];
        $text = "{$table}.data->>'{$key}'";
        $jsonb = "{$table}.data->'{$key}'";
        $numeric = "({$text})::numeric";
        $isNumeric = in_array($field['type'], ['number', 'price'], true);
        $isDate = $field['type'] === 'date';
        $accessor = $isNumeric ? $numeric : ($isDate ? "({$text})::date" : $text);

        switch ($operator) {
            case 'eq':
                if ($field['type'] === 'boolean') {
                    $query->whereRaw("{$table}.data @> ?", [json_encode([$key => filter_var($value, FILTER_VALIDATE_BOOLEAN)])]);
                } elseif ($isNumeric) {
                    $query->whereRaw("{$accessor} = ?", [$value + 0]);
                } else {
                    $query->whereRaw("{$text} = ?", [(string) $value]);
                }
                break;
            case 'neq':
                if ($field['type'] === 'boolean') {
                    $query->whereRaw("NOT ({$table}.data @> ?)", [json_encode([$key => filter_var($value, FILTER_VALIDATE_BOOLEAN)])]);
                } else {
                    $query->whereRaw("{$text} IS DISTINCT FROM ?", [(string) $value]);
                }
                break;
            case 'contains':
                $query->whereRaw("{$text} ILIKE ?", ['%' . $this->escapeLike((string) $value) . '%']);
                break;
            case 'starts_with':
                $query->whereRaw("{$text} ILIKE ?", [$this->escapeLike((string) $value) . '%']);
                break;
            case 'gt': $query->whereRaw("{$accessor} > ?", [$this->castScalar($value, $isNumeric)]); break;
            case 'gte': $query->whereRaw("{$accessor} >= ?", [$this->castScalar($value, $isNumeric)]); break;
            case 'lt': $query->whereRaw("{$accessor} < ?", [$this->castScalar($value, $isNumeric)]); break;
            case 'lte': $query->whereRaw("{$accessor} <= ?", [$this->castScalar($value, $isNumeric)]); break;
            case 'between':
                $query->whereRaw("{$accessor} BETWEEN ? AND ?", [
                    $this->castScalar($value[0], $isNumeric),
                    $this->castScalar($value[1], $isNumeric),
                ]);
                break;
            case 'in':
            case 'not_in':
                $values = array_map('strval', (array) $value);
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $not = $operator === 'not_in' ? 'NOT ' : '';
                $query->whereRaw("{$not}({$text} IN ({$placeholders}))", $values);
                break;
            case 'has_any':
                $query->where(function ($w) use ($table, $key, $value) {
                    foreach ((array) $value as $v) {
                        $w->orWhereRaw("{$table}.data @> ?", [json_encode([$key => [(string) $v]])]);
                    }
                });
                break;
            case 'is_empty':
                $query->whereRaw("(NOT jsonb_exists({$table}.data, '{$key}') OR {$text} = '' OR {$jsonb} IN ('[]'::jsonb, 'null'::jsonb))");
                break;
            case 'not_empty':
                $query->whereRaw("(jsonb_exists({$table}.data, '{$key}') AND {$text} <> '' AND {$jsonb} NOT IN ('[]'::jsonb, 'null'::jsonb))");
                break;
        }
    }

    private function applySort(Builder $query, ContentCollection $collection, string $fieldPath, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if (in_array($fieldPath, ['published_at', 'created_at', 'updated_at', 'title', 'position'], true)) {
            $query->orderBy("records.{$fieldPath}", $direction);

            return;
        }

        $field = $collection->field($fieldPath);
        $accessor = in_array($field['type'] ?? '', ['number', 'price'], true)
            ? "records.data->'{$fieldPath}'"
            : "records.data->>'{$fieldPath}'";
        $query->orderByRaw("{$accessor} {$direction} NULLS LAST");
    }

    private function runAggregate(Builder $query, ContentCollection $collection, array $aggregate): array
    {
        $metrics = $aggregate['metrics'];
        $groupBy = $aggregate['group_by'];

        $selects = [];
        $columns = [];
        foreach ($metrics as $metric) {
            if ($metric['fn'] === 'count') {
                $selects[] = 'count(DISTINCT records.id) as count';
                $columns[] = ['key' => 'count', 'label' => 'Count'];
            } else {
                $alias = "{$metric['fn']}_{$metric['field']}";
                $selects[] = "round({$metric['fn']}((records.data->>'{$metric['field']}')::numeric), 2) as {$alias}";
                $fieldLabel = $collection->field($metric['field'])['label'] ?? $metric['field'];
                $columns[] = ['key' => $alias, 'label' => ucfirst($metric['fn']) . ' ' . $fieldLabel];
            }
        }

        if ($groupBy === null) {
            $row = (array) $query->selectRaw(implode(', ', $selects))->toBase()->first();
            if (count($metrics) === 1) {
                $key = $columns[0]['key'];

                return ['type' => 'value', 'value' => $row[$key] !== null ? $row[$key] + 0 : 0, 'label' => $columns[0]['label']];
            }

            return ['type' => 'table', 'columns' => $columns, 'rows' => [array_map(fn ($v) => $v !== null ? $v + 0 : 0, $row)], 'total' => 1];
        }

        $groupField = $collection->field($groupBy);
        $groupLabel = $groupField['label'] ?? $groupBy;

        if ($groupField['type'] === 'relation') {
            $rows = $query->toBase()
                ->join('record_relations as grr', function ($join) use ($groupBy) {
                    $join->on('grr.from_record_id', '=', 'records.id')->where('grr.relation_key', $groupBy);
                })
                ->join('records as gtr', 'gtr.id', '=', 'grr.to_record_id')
                ->selectRaw('gtr.title as "group", ' . implode(', ', $selects))
                ->groupBy('gtr.title')
                ->orderByDesc(DB::raw(str_contains($selects[0], ' as count') ? 'count(DISTINCT records.id)' : '1'))
                ->limit(100)
                ->get();
        } else {
            $accessor = "records.data->>'{$groupBy}'";
            $rows = $query->toBase()
                ->selectRaw("{$accessor} as \"group\", " . implode(', ', $selects))
                ->whereRaw("jsonb_exists(records.data, '{$groupBy}')")
                ->groupByRaw($accessor)
                ->orderByRaw('2 DESC')
                ->limit(100)
                ->get();
        }

        $out = $rows->map(function ($row) {
            $arr = (array) $row;
            foreach ($arr as $k => $v) {
                if ($k !== 'group' && $v !== null) {
                    $arr[$k] = $v + 0;
                }
            }

            return $arr;
        })->all();

        return [
            'type' => 'table',
            'columns' => array_merge([['key' => 'group', 'label' => $groupLabel]], $columns),
            'rows' => $out,
            'total' => count($out),
        ];
    }

    private function resolveValue(mixed $value, array $params): mixed
    {
        if (is_array($value) && isset($value['param'])) {
            if (!array_key_exists($value['param'], $params)) {
                throw ValidationException::withMessages([
                    $value['param'] => "Missing required parameter '{$value['param']}'.",
                ]);
            }

            return $params[$value['param']];
        }

        return $value;
    }

    private function castScalar(mixed $value, bool $isNumeric): mixed
    {
        return $isNumeric ? (is_numeric($value) ? $value + 0 : 0) : (string) $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
