<?php

namespace App\Domain\Collections\Queries;

use App\Models\ContentCollection;

/**
 * Track G-Q friendliness contract: the live plain-language preview of a
 * Simple-mode definition — "Show Parts where In stock is yes and Price is
 * under 50, grouped by Manufacturer, showing count and average Price."
 * Server-generated so the admin UI, the wizard and docs all read one voice.
 */
class QuerySentence
{
    private const OPERATOR_PHRASES = [
        'eq' => 'is',
        'neq' => 'is not',
        'contains' => 'contains',
        'starts_with' => 'starts with',
        'gt' => 'is above',
        'gte' => 'is at least',
        'lt' => 'is under',
        'lte' => 'is at most',
        'between' => 'is between',
        'in' => 'is one of',
        'not_in' => 'is none of',
        'has_any' => 'includes any of',
        'is_empty' => 'is empty',
        'not_empty' => 'is set',
    ];

    public function describe(array $definition, ContentCollection $collection): string
    {
        $parts = ['Show ' . mb_strtolower($collection->name)];

        if (!empty($definition['filters'])) {
            $parts[] = 'where ' . $this->group($definition['filters'], $collection);
        }

        if (!empty($definition['aggregate'])) {
            $agg = $definition['aggregate'];
            if ($agg['group_by']) {
                $parts[] = 'grouped by ' . mb_strtolower($this->fieldLabel($agg['group_by'], $collection));
            }
            $metrics = array_map(function ($metric) use ($collection) {
                return $metric['fn'] === 'count'
                    ? 'count'
                    : $this->fnWord($metric['fn']) . ' ' . mb_strtolower($this->fieldLabel($metric['field'], $collection));
            }, $agg['metrics']);
            $parts[] = 'showing ' . $this->joinList($metrics);
        } else {
            foreach ($definition['sort'] ?? [] as $i => $sort) {
                $label = mb_strtolower($this->fieldLabel($sort['field'], $collection));
                $direction = $sort['direction'] === 'desc' ? 'highest first' : 'lowest first';
                if (in_array($sort['field'], ['published_at', 'created_at'], true)) {
                    $direction = $sort['direction'] === 'desc' ? 'newest first' : 'oldest first';
                    $label = $sort['field'] === 'published_at' ? 'publish date' : 'creation date';
                }
                $parts[] = ($i === 0 ? 'sorted by ' : 'then by ') . $label . ' (' . $direction . ')';
            }
            if (($definition['limit'] ?? 100) < 100) {
                $parts[] = 'limited to ' . $definition['limit'];
            }
        }

        return implode(', ', $parts) . '.';
    }

    private function group(array $group, ContentCollection $collection): string
    {
        $glue = $group['op'] === 'or' ? ' or ' : ' and ';
        $parts = [];
        foreach ($group['children'] as $child) {
            if (isset($child['children'])) {
                $parts[] = '(' . $this->group($child, $collection) . ')';
            } else {
                $parts[] = $this->condition($child, $collection);
            }
        }

        return implode($glue, $parts);
    }

    private function condition(array $condition, ContentCollection $collection): string
    {
        $label = $this->fieldLabel($condition['field'], $collection);
        $phrase = self::OPERATOR_PHRASES[$condition['operator']] ?? $condition['operator'];
        $value = $condition['value'];

        if (in_array($condition['operator'], ['is_empty', 'not_empty'], true)) {
            return "{$label} {$phrase}";
        }
        if (is_array($value) && isset($value['param'])) {
            $value = '‹' . $value['param'] . '›';
        } elseif (is_array($value)) {
            $value = $condition['operator'] === 'between'
                ? "{$value[0]} and {$value[1]}"
                : $this->joinList(array_map('strval', $value), 'or');
        } elseif (is_bool($value)) {
            return "{$label} is " . ($value ? 'yes' : 'no');
        }

        return "{$label} {$phrase} {$value}";
    }

    private function fieldLabel(string $path, ContentCollection $collection): string
    {
        $segments = explode('.', $path);
        if (count($segments) === 2) {
            $relation = $collection->field($segments[0]);
            $target = $relation ? \App\Models\ContentCollection::find($relation['relation']['collection_id']) : null;
            $targetLabel = $target?->field($segments[1])['label'] ?? $segments[1];

            return ($relation['label'] ?? $segments[0]) . "’s {$targetLabel}";
        }

        return $collection->field($path)['label'] ?? $path;
    }

    private function fnWord(string $fn): string
    {
        return ['sum' => 'total', 'avg' => 'average', 'min' => 'lowest', 'max' => 'highest'][$fn] ?? $fn;
    }

    private function joinList(array $items, string $word = 'and'): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items) . " {$word} {$last}";
    }
}
