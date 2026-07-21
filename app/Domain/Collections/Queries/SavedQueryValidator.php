<?php

namespace App\Domain\Collections\Queries;

use App\Models\ContentCollection;
use App\Models\SavedQuery;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

/**
 * Track G-Q — validates + normalizes a Simple-mode query definition against
 * the source collection's schema. Everything the compiler will interpolate
 * (field keys, operators, directions, group-by) must pass through here; the
 * compiler never sees unvalidated input.
 *
 * Definition shape:
 * {
 *   collection_id,
 *   filters: {op: and|or, children: [ {field, operator, value} | group ]},   // nesting ≤3
 *   sort: [{field, direction}],                                              // ≤3 keys
 *   limit: 1..500,
 *   aggregate: {group_by: fieldKey|null, metrics: [{fn, field?}]}            // optional
 * }
 * Field paths may traverse ONE relation hop: "supplier.lead_time"
 * (the spec's depth-2 wall, enforced here).
 */
class SavedQueryValidator
{
    private const MAX_GROUP_DEPTH = 3;
    private const MAX_CHILDREN = 20;
    private const MAX_SORT_KEYS = 3;
    private const MAX_METRICS = 4;

    /** operator => allowed field types ('*' = any scalar-bearing field) */
    public const OPERATORS = [
        'eq' => ['text', 'sku', 'email', 'url', 'phone', 'select', 'number', 'price', 'date', 'boolean'],
        'neq' => ['text', 'sku', 'email', 'url', 'phone', 'select', 'number', 'price', 'date', 'boolean'],
        'contains' => ['text', 'sku', 'email', 'url', 'phone', 'rich_text'],
        'starts_with' => ['text', 'sku', 'email', 'url', 'phone'],
        'gt' => ['number', 'price', 'date'],
        'gte' => ['number', 'price', 'date'],
        'lt' => ['number', 'price', 'date'],
        'lte' => ['number', 'price', 'date'],
        'between' => ['number', 'price', 'date'],
        'in' => ['select', 'text', 'sku'],
        'not_in' => ['select', 'text', 'sku'],
        'has_any' => ['multi_select'],
        'is_empty' => ['*'],
        'not_empty' => ['*'],
    ];

    public function validate(array $definition, Site $site, ?SavedQuery $existing = null): array
    {
        $collectionId = $definition['collection_id'] ?? null;
        $collection = is_string($collectionId)
            ? ContentCollection::where('site_id', $site->id)->find($collectionId)
            : null;
        if (!$collection) {
            $this->fail('collection_id', 'Pick the collection this query reads.');
        }

        $out = ['collection_id' => $collection->id];

        $filters = $definition['filters'] ?? null;
        $out['filters'] = $filters === null || $filters === []
            ? null
            : $this->validateGroup($filters, $collection, 'filters', 1);

        $out['sort'] = $this->validateSort($definition['sort'] ?? [], $collection);

        $limit = $definition['limit'] ?? 100;
        if (!is_int($limit) || $limit < 1 || $limit > 500) {
            $this->fail('limit', 'Limit is a number between 1 and 500.');
        }
        $out['limit'] = $limit;

        if (isset($definition['aggregate']) && is_array($definition['aggregate']) && $definition['aggregate'] !== []) {
            $out['aggregate'] = $this->validateAggregate($definition['aggregate'], $collection);
        }

        return $out;
    }

    /**
     * Resolve a (possibly dotted) field path against the schema.
     *
     * @return array{field: array, relation: ?array, path: string} field def +
     *         the relation field traversed (null for local fields)
     */
    public function resolvePath(string $path, ContentCollection $collection, string $errorKey): array
    {
        $segments = explode('.', $path);
        if (count($segments) > 2) {
            $this->fail($errorKey, "'{$path}': relations can be traversed at most one hop deep (e.g. supplier.lead_time).");
        }

        if (count($segments) === 1) {
            $field = $collection->field($path);
            if (!$field) {
                $this->fail($errorKey, "Unknown field '{$path}'.");
            }

            return ['field' => $field, 'relation' => null, 'path' => $path];
        }

        [$relationKey, $targetKey] = $segments;
        $relationField = $collection->field($relationKey);
        if (!$relationField || $relationField['type'] !== 'relation') {
            $this->fail($errorKey, "'{$relationKey}' is not a relation field.");
        }
        $target = ContentCollection::find($relationField['relation']['collection_id']);
        $targetField = $target?->field($targetKey);
        if (!$targetField) {
            $this->fail($errorKey, "Unknown field '{$targetKey}' on the related collection.");
        }
        if ($targetField['type'] === 'relation') {
            $this->fail($errorKey, 'Relations of relations are beyond the depth-2 wall.');
        }

        return ['field' => $targetField, 'relation' => $relationField, 'path' => $path];
    }

    private function validateGroup(mixed $group, ContentCollection $collection, string $key, int $depth): array
    {
        if (!is_array($group)) {
            $this->fail($key, 'Invalid filter group.');
        }
        if ($depth > self::MAX_GROUP_DEPTH) {
            $this->fail($key, 'Filter groups nest at most ' . self::MAX_GROUP_DEPTH . ' levels.');
        }

        $op = $group['op'] ?? 'and';
        if (!in_array($op, ['and', 'or'], true)) {
            $this->fail("{$key}.op", "Groups combine with 'and' or 'or'.");
        }

        $children = $group['children'] ?? [];
        if (!is_array($children) || $children === []) {
            $this->fail("{$key}.children", 'A filter group needs at least one condition.');
        }
        if (count($children) > self::MAX_CHILDREN) {
            $this->fail("{$key}.children", 'At most ' . self::MAX_CHILDREN . ' conditions per group.');
        }

        $clean = ['op' => $op, 'children' => []];
        foreach (array_values($children) as $i => $child) {
            $childKey = "{$key}.children.{$i}";
            if (isset($child['children']) || isset($child['op']) && !isset($child['field'])) {
                $clean['children'][] = $this->validateGroup($child, $collection, $childKey, $depth + 1);
            } else {
                $clean['children'][] = $this->validateCondition($child, $collection, $childKey);
            }
        }

        return $clean;
    }

    private function validateCondition(mixed $condition, ContentCollection $collection, string $key): array
    {
        if (!is_array($condition) || !is_string($condition['field'] ?? null)) {
            $this->fail($key, 'Invalid condition.');
        }

        $resolved = $this->resolvePath($condition['field'], $collection, "{$key}.field");
        $field = $resolved['field'];

        $operator = $condition['operator'] ?? null;
        $allowed = self::OPERATORS[$operator] ?? null;
        if ($allowed === null) {
            $this->fail("{$key}.operator", "Unknown operator '{$operator}'.");
        }
        if ($allowed !== ['*'] && !in_array($field['type'], $allowed, true)) {
            $this->fail("{$key}.operator", "'{$operator}' doesn't apply to {$field['type']} fields.");
        }

        $value = $condition['value'] ?? null;
        $value = $this->validateValue($operator, $field, $value, $key);

        return ['field' => $resolved['path'], 'operator' => $operator, 'value' => $value];
    }

    private function validateValue(string $operator, array $field, mixed $value, string $key): mixed
    {
        if (in_array($operator, ['is_empty', 'not_empty'], true)) {
            return null;
        }

        // A value may be a declared-public-param placeholder: {"param": "max_price"}
        if (is_array($value) && isset($value['param'])) {
            if (!is_string($value['param']) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $value['param'])) {
                $this->fail("{$key}.value", 'Invalid parameter reference.');
            }

            return ['param' => $value['param']];
        }

        if (in_array($operator, ['in', 'not_in', 'has_any'], true)) {
            if (!is_array($value) || $value === [] || count($value) > 50 || array_filter($value, fn ($v) => !is_scalar($v)) !== []) {
                $this->fail("{$key}.value", 'Provide a list of values (max 50).');
            }

            return array_values(array_map('strval', $value));
        }

        if ($operator === 'between') {
            if (!is_array($value) || count($value) !== 2 || !is_scalar($value[0] ?? null) || !is_scalar($value[1] ?? null)) {
                $this->fail("{$key}.value", 'Between needs exactly two values.');
            }

            return [$value[0], $value[1]];
        }

        if ($field['type'] === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if (in_array($field['type'], ['number', 'price'], true) && !in_array($field['type'], ['date'], true)) {
            if (!is_numeric($value)) {
                $this->fail("{$key}.value", 'Provide a number.');
            }

            return $value + 0;
        }
        if (!is_scalar($value) || mb_strlen((string) $value) > 500) {
            $this->fail("{$key}.value", 'Invalid value.');
        }

        return (string) $value;
    }

    private function validateSort(mixed $sort, ContentCollection $collection): array
    {
        if (!is_array($sort)) {
            $this->fail('sort', 'Invalid sort.');
        }
        if (count($sort) > self::MAX_SORT_KEYS) {
            $this->fail('sort', 'At most ' . self::MAX_SORT_KEYS . ' sort keys.');
        }

        $clean = [];
        foreach (array_values($sort) as $i => $entry) {
            $fieldPath = $entry['field'] ?? null;
            if (!is_string($fieldPath)) {
                $this->fail("sort.{$i}", 'Invalid sort key.');
            }
            if (!in_array($fieldPath, ['published_at', 'created_at', 'updated_at', 'title', 'position'], true)) {
                $resolved = $this->resolvePath($fieldPath, $collection, "sort.{$i}.field");
                if ($resolved['relation'] !== null) {
                    $this->fail("sort.{$i}.field", 'Sorting by related fields is not supported.');
                }
                if (in_array($resolved['field']['type'], ['relation', 'gallery', 'rich_text', 'image', 'file', 'computed'], true)) {
                    $this->fail("sort.{$i}.field", "Can't sort by {$resolved['field']['type']} fields.");
                }
            }
            $direction = ($entry['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $clean[] = ['field' => $fieldPath, 'direction' => $direction];
        }

        return $clean;
    }

    private function validateAggregate(array $aggregate, ContentCollection $collection): array
    {
        $out = ['group_by' => null, 'metrics' => []];

        if (isset($aggregate['group_by']) && $aggregate['group_by'] !== null && $aggregate['group_by'] !== '') {
            $groupBy = $aggregate['group_by'];
            $resolved = $this->resolvePath((string) $groupBy, $collection, 'aggregate.group_by');
            if ($resolved['relation'] !== null) {
                $this->fail('aggregate.group_by', 'Group by a local field (select, boolean or relation).');
            }
            if (!in_array($resolved['field']['type'], ['select', 'boolean', 'relation'], true)) {
                $this->fail('aggregate.group_by', 'Group by select, boolean or relation fields.');
            }
            $out['group_by'] = $resolved['path'];
        }

        $metrics = $aggregate['metrics'] ?? [];
        if (!is_array($metrics) || $metrics === [] || count($metrics) > self::MAX_METRICS) {
            $this->fail('aggregate.metrics', 'Pick between 1 and ' . self::MAX_METRICS . ' metrics.');
        }

        foreach (array_values($metrics) as $i => $metric) {
            $fn = $metric['fn'] ?? null;
            if (!in_array($fn, ['count', 'sum', 'avg', 'min', 'max'], true)) {
                $this->fail("aggregate.metrics.{$i}", 'Metrics are count, sum, avg, min or max.');
            }
            if ($fn === 'count') {
                $out['metrics'][] = ['fn' => 'count'];
                continue;
            }
            $fieldPath = $metric['field'] ?? null;
            if (!is_string($fieldPath)) {
                $this->fail("aggregate.metrics.{$i}.field", "{$fn} needs a number or price field.");
            }
            $resolved = $this->resolvePath($fieldPath, $collection, "aggregate.metrics.{$i}.field");
            if ($resolved['relation'] !== null || !in_array($resolved['field']['type'], ['number', 'price'], true)) {
                $this->fail("aggregate.metrics.{$i}.field", "{$fn} works on local number or price fields.");
            }
            $out['metrics'][] = ['fn' => $fn, 'field' => $resolved['path']];
        }

        return $out;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
