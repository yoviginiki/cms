<?php

namespace App\Domain\Collections\Queries;

use App\Models\ContentCollection;

/**
 * Track G-Q2 — the mode bridge: render a validated Simple-mode definition as
 * the exact SQL it means, against the site's scoped views. This is the
 * primary way users learn SQL on their own data — copyable straight into
 * Advanced mode, where it runs identically.
 *
 * Semantics mirror SimpleQueryCompiler; the output is human-readable
 * (indented, view/column names, literal params as ‹placeholders›).
 */
class SimpleToSql
{
    public function __construct(private ScopedViewManager $views) {}

    public function render(array $definition, ContentCollection $collection): string
    {
        $view = $this->views->collectionViewName($collection);

        $select = $this->selectClause($definition, $collection, $view);
        $where = !empty($definition['filters'])
            ? "\nWHERE " . $this->group($definition['filters'], $collection, $view)
            : '';

        if (!empty($definition['aggregate'])) {
            return $this->aggregateSql($definition, $collection, $view, $where);
        }

        $orderBy = '';
        if (!empty($definition['sort'])) {
            $keys = array_map(
                fn ($s) => $this->columnRef($s['field'], $collection, $view) . ' ' . strtoupper($s['direction']),
                $definition['sort'],
            );
            $orderBy = "\nORDER BY " . implode(', ', $keys);
        }

        $limit = "\nLIMIT " . ($definition['limit'] ?? 100);

        return "SELECT {$select}\nFROM {$view}{$where}{$orderBy}{$limit}";
    }

    private function selectClause(array $definition, ContentCollection $collection, string $view): string
    {
        $cols = ['record_title'];
        foreach ($collection->fields() as $field) {
            if (in_array($field['type'], ['relation', 'gallery', 'rich_text', 'computed'], true)) {
                continue;
            }
            $cols[] = $field['key'];
        }

        return implode(', ', array_slice($cols, 0, 8));
    }

    private function aggregateSql(array $definition, ContentCollection $collection, string $view, string $where): string
    {
        $agg = $definition['aggregate'];
        $selects = [];
        $groupCol = null;

        if ($agg['group_by']) {
            $groupField = $collection->field($agg['group_by']);
            if ($groupField['type'] === 'relation') {
                // Grouping by a relation joins the rel_ view on to_title.
                $relView = $this->views->relationViewName($collection, $agg['group_by']);
                $selects[] = "{$relView}.to_title AS group";
                $from = "{$view}\nJOIN {$relView} ON {$relView}.from_id = {$view}.id";
                $groupCol = "{$relView}.to_title";
            } else {
                $selects[] = "{$agg['group_by']} AS group";
                $from = $view;
                $groupCol = $agg['group_by'];
            }
        } else {
            $from = $view;
        }

        foreach ($agg['metrics'] as $metric) {
            $selects[] = $metric['fn'] === 'count'
                ? 'count(*) AS count'
                : "round({$metric['fn']}({$metric['field']}), 2) AS {$metric['fn']}_{$metric['field']}";
        }

        $sql = 'SELECT ' . implode(', ', $selects) . "\nFROM {$from}{$where}";
        if ($groupCol) {
            $sql .= "\nGROUP BY {$groupCol}\nORDER BY count DESC";
        }

        return $sql;
    }

    private function group(array $group, ContentCollection $collection, string $view): string
    {
        $glue = $group['op'] === 'or' ? ' OR ' : ' AND ';
        $parts = [];
        foreach ($group['children'] as $child) {
            $parts[] = isset($child['children'])
                ? '(' . $this->group($child, $collection, $view) . ')'
                : $this->condition($child, $collection, $view);
        }

        return implode($glue, $parts);
    }

    private function condition(array $condition, ContentCollection $collection, string $view): string
    {
        // Relation-hop condition → EXISTS over the rel_ view, joined to the
        // target collection's col_ view (the hopped field lives on the target
        // record's data, not on the relation's pivot).
        if (str_contains($condition['field'], '.')) {
            [$relKey, $targetKey] = explode('.', $condition['field']);
            $relField = $collection->field($relKey);
            $target = $relField ? ContentCollection::find($relField['relation']['collection_id']) : null;
            $relView = $this->views->relationViewName($collection, $relKey);
            $targetView = $target ? $this->views->collectionViewName($target) : 'col_unknown';
            $inner = $this->comparison("{$targetView}.{$targetKey}", $condition['operator'], $condition['value']);

            return "EXISTS (SELECT 1 FROM {$relView}"
                . " JOIN {$targetView} ON {$targetView}.id = {$relView}.to_id"
                . " WHERE {$relView}.from_id = {$view}.id AND {$inner})";
        }

        return $this->comparison($condition['field'], $condition['operator'], $condition['value']);
    }

    private function comparison(string $column, string $operator, mixed $value): string
    {
        $v = $this->valueLiteral($value);

        return match ($operator) {
            'eq' => "{$column} = {$v}",
            'neq' => "{$column} IS DISTINCT FROM {$v}",
            'contains' => "{$column} ILIKE '%' || {$v} || '%'",
            'starts_with' => "{$column} ILIKE {$v} || '%'",
            'gt' => "{$column} > {$v}",
            'gte' => "{$column} >= {$v}",
            'lt' => "{$column} < {$v}",
            'lte' => "{$column} <= {$v}",
            'between' => "{$column} BETWEEN " . $this->valueLiteral($value[0]) . ' AND ' . $this->valueLiteral($value[1]),
            'in' => "{$column} IN (" . implode(', ', array_map([$this, 'valueLiteral'], (array) $value)) . ')',
            'not_in' => "{$column} NOT IN (" . implode(', ', array_map([$this, 'valueLiteral'], (array) $value)) . ')',
            'has_any' => "{$column} ?| array[" . implode(', ', array_map([$this, 'valueLiteral'], (array) $value)) . ']',
            'is_empty' => "({$column} IS NULL)",
            'not_empty' => "({$column} IS NOT NULL)",
            default => '1=1',
        };
    }

    private function columnRef(string $field, ContentCollection $collection, string $view): string
    {
        return in_array($field, ['published_at', 'created_at', 'updated_at', 'position'], true)
            ? "record_{$field}"
            : ($field === 'title' ? 'record_title' : $field);
    }

    private function valueLiteral(mixed $value): string
    {
        if (is_array($value) && isset($value['param'])) {
            return '‹' . $value['param'] . '›';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
