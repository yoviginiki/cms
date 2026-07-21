<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\FieldTypes;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

/**
 * Validates + normalizes a collection schema definition (the admin's field
 * design), never record data. Returns the canonical schema array to store or
 * throws ValidationException with per-field messages.
 */
class CollectionSchemaValidator
{
    private const MAX_FIELDS = 60;
    private const MAX_OPTIONS = 100;
    private const MAX_PIVOT_FIELDS = 10;

    /** Types a default value may be defined on (no assets, no relations). */
    private const DEFAULTABLE_TYPES = [
        'text', 'rich_text', 'number', 'price', 'boolean', 'select',
        'multi_select', 'date', 'email', 'url', 'phone', 'sku',
    ];

    public function __construct(private RecordDataProcessor $processor)
    {
    }

    public function validate(array $schema, Site $site, ?ContentCollection $existing = null): array
    {
        $fields = $schema['fields'] ?? [];
        if (!is_array($fields)) {
            $this->fail('fields', 'Invalid fields definition.');
        }
        // Empty schema is legal at creation time — the schema editor adds
        // fields next; records can't be entered until a title field exists.
        if ($fields === []) {
            return ['fields' => [], 'title_field' => null, 'slug_source' => null];
        }
        if (count($fields) > self::MAX_FIELDS) {
            $this->fail('fields', 'A collection may have at most ' . self::MAX_FIELDS . ' fields.');
        }

        $normalized = [];
        $seenKeys = [];

        foreach (array_values($fields) as $i => $field) {
            if (!is_array($field)) {
                $this->fail("fields.{$i}", 'Invalid field definition.');
            }
            $normalized[] = $this->validateField($field, $i, $seenKeys, $site, $existing);
        }

        $keys = array_column($normalized, 'key');

        $titleField = $schema['title_field'] ?? null;
        if (!is_string($titleField) || !in_array($titleField, $keys, true)) {
            $this->fail('title_field', 'Pick which field is the record title.');
        }
        $titleType = $normalized[array_search($titleField, $keys, true)]['type'];
        if (!in_array($titleType, ['text', 'sku'], true)) {
            $this->fail('title_field', 'The title field must be a text or SKU field.');
        }

        $slugSource = $schema['slug_source'] ?? $titleField;
        if (!is_string($slugSource) || !in_array($slugSource, $keys, true)) {
            $this->fail('slug_source', 'The slug source must be one of the defined fields.');
        }

        return [
            'fields' => $normalized,
            'title_field' => $titleField,
            'slug_source' => $slugSource,
        ];
    }

    private function validateField(array $field, int $i, array &$seenKeys, Site $site, ?ContentCollection $existing): array
    {
        $path = "fields.{$i}";

        $key = $field['key'] ?? null;
        if (!is_string($key) || !preg_match(FieldTypes::KEY_PATTERN, $key)) {
            $this->fail("{$path}.key", 'Field keys are lowercase letters, digits and underscores, starting with a letter (max 40 chars).');
        }
        if (in_array($key, FieldTypes::RESERVED_KEYS, true)) {
            $this->fail("{$path}.key", "'{$key}' is a reserved key.");
        }
        if (isset($seenKeys[$key])) {
            $this->fail("{$path}.key", "Duplicate field key '{$key}'.");
        }
        $seenKeys[$key] = true;

        $label = $field['label'] ?? null;
        if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 80) {
            $this->fail("{$path}.label", 'Every field needs a label (max 80 chars).');
        }

        $type = $field['type'] ?? null;
        if (!in_array($type, FieldTypes::TYPES, true)) {
            $this->fail("{$path}.type", 'Unknown field type.');
        }

        $out = [
            'key' => $key,
            'label' => trim($label),
            'type' => $type,
            'required' => (bool) ($field['required'] ?? false),
            'unique' => (bool) ($field['unique'] ?? false),
            'searchable' => (bool) ($field['searchable'] ?? false),
            'facetable' => (bool) ($field['facetable'] ?? false),
            'show_in_list' => (bool) ($field['show_in_list'] ?? false),
        ];

        if (is_string($field['description'] ?? null) && trim($field['description']) !== '') {
            $out['description'] = mb_substr(trim($field['description']), 0, 200);
        }

        if ($out['unique'] && !in_array($type, FieldTypes::UNIQUE_TYPES, true)) {
            $this->fail("{$path}.unique", "The unique toggle isn't available on {$type} fields.");
        }
        if ($out['searchable'] && !in_array($type, FieldTypes::SEARCHABLE_TYPES, true)) {
            $this->fail("{$path}.searchable", "{$type} fields can't be searchable.");
        }
        if ($out['facetable'] && !in_array($type, FieldTypes::FACETABLE_TYPES, true)) {
            $this->fail("{$path}.facetable", "{$type} fields can't be facets.");
        }

        if (in_array($type, FieldTypes::OPTION_TYPES, true)) {
            $out['options'] = $this->validateOptions($field['options'] ?? null, $path);
        }

        if ($type === 'relation') {
            $out['relation'] = $this->validateRelation($field['relation'] ?? null, $path, $site, $existing);
        }

        if ($type === 'computed') {
            if ($out['required'] || $out['unique'] || $out['searchable'] || $out['facetable']) {
                $this->fail("{$path}.type", 'Computed fields are display-only (no required/unique/search/facet).');
            }
            $out['computed'] = $this->validateComputed($field['computed'] ?? null, $path, $site);
        }

        $settings = $field['settings'] ?? [];
        if (is_array($settings) && $settings !== []) {
            $out['settings'] = $this->validateSettings($settings, $path);
        }

        // Default value: stored in canonical (processed) form so applying it
        // at record creation can never fail validation later.
        $default = $field['default'] ?? null;
        if ($default !== null && $default !== '' && $default !== []) {
            if (!in_array($type, self::DEFAULTABLE_TYPES, true)) {
                $this->fail("{$path}.default", "Defaults aren't available on {$type} fields.");
            }
            try {
                $processed = $this->processor->processFields([$out], [$key => $default]);
            } catch (ValidationException) {
                $this->fail("{$path}.default", 'The default value is not valid for this field.');
            }
            if (array_key_exists($key, $processed)) {
                $out['default'] = $processed[$key];
            }
        }

        return $out;
    }

    /**
     * Computed rollups: {fn: count|sum, collection_id, relation_key,
     * sum_field?} — "count/sum of records in <collection> whose <relation_key>
     * points at this record".
     */
    private function validateComputed(mixed $config, string $path, Site $site): array
    {
        if (!is_array($config)) {
            $this->fail("{$path}.computed", 'Computed fields need a rollup configuration.');
        }

        $fn = $config['fn'] ?? null;
        if (!in_array($fn, ['count', 'sum'], true)) {
            $this->fail("{$path}.computed.fn", "Rollup is 'count' or 'sum'.");
        }

        $targetId = $config['collection_id'] ?? null;
        $target = is_string($targetId)
            ? ContentCollection::where('site_id', $site->id)->where('id', $targetId)->first()
            : null;
        if (!$target) {
            $this->fail("{$path}.computed.collection_id", 'Source collection not found on this site.');
        }

        $relationKey = $config['relation_key'] ?? null;
        $relationField = is_string($relationKey) ? $target->field($relationKey) : null;
        if (!$relationField || $relationField['type'] !== 'relation') {
            $this->fail("{$path}.computed.relation_key", "Pick the relation field on '{$target->name}' that points here.");
        }

        $out = ['fn' => $fn, 'collection_id' => $targetId, 'relation_key' => $relationKey];

        if ($fn === 'sum') {
            $sumField = $config['sum_field'] ?? null;
            $sumDef = is_string($sumField) ? $target->field($sumField) : null;
            if (!$sumDef || !in_array($sumDef['type'], ['number', 'price'], true)) {
                $this->fail("{$path}.computed.sum_field", 'Sum needs a number or price field on the source collection.');
            }
            $out['sum_field'] = $sumField;
        }

        return $out;
    }

    private function validateOptions(mixed $options, string $path): array
    {
        if (!is_array($options) || $options === []) {
            $this->fail("{$path}.options", 'Select fields need at least one option.');
        }
        if (count($options) > self::MAX_OPTIONS) {
            $this->fail("{$path}.options", 'At most ' . self::MAX_OPTIONS . ' options.');
        }

        $clean = [];
        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '' || mb_strlen($option) > 80) {
                $this->fail("{$path}.options", 'Options are non-empty strings, max 80 chars.');
            }
            $option = trim($option);
            if (in_array($option, $clean, true)) {
                $this->fail("{$path}.options", "Duplicate option '{$option}'.");
            }
            $clean[] = $option;
        }

        return $clean;
    }

    private function validateRelation(mixed $relation, string $path, Site $site, ?ContentCollection $existing): array
    {
        if (!is_array($relation)) {
            $this->fail("{$path}.relation", 'Relation fields need a target collection.');
        }

        $targetId = $relation['collection_id'] ?? null;
        $isSelf = $existing && $targetId === $existing->id;
        if (!$isSelf) {
            $target = is_string($targetId)
                ? ContentCollection::where('site_id', $site->id)->where('id', $targetId)->first()
                : null;
            if (!$target) {
                $this->fail("{$path}.relation.collection_id", 'Target collection not found on this site.');
            }
        }

        $mode = $relation['mode'] ?? null;
        if (!in_array($mode, ['one', 'many'], true)) {
            $this->fail("{$path}.relation.mode", "Relation mode is 'one' or 'many'.");
        }

        $out = ['collection_id' => $targetId, 'mode' => $mode];

        $pivotFields = $relation['pivot_fields'] ?? [];
        if ($pivotFields !== [] && $mode !== 'many') {
            $this->fail("{$path}.relation.pivot_fields", 'Pivot fields only exist on many-to-many relations.');
        }
        if (is_array($pivotFields) && $pivotFields !== []) {
            if (count($pivotFields) > self::MAX_PIVOT_FIELDS) {
                $this->fail("{$path}.relation.pivot_fields", 'At most ' . self::MAX_PIVOT_FIELDS . ' pivot fields.');
            }
            $seen = [];
            $clean = [];
            foreach (array_values($pivotFields) as $j => $pf) {
                $pfPath = "{$path}.relation.pivot_fields.{$j}";
                $pfKey = $pf['key'] ?? null;
                if (!is_string($pfKey) || !preg_match(FieldTypes::KEY_PATTERN, $pfKey) || in_array($pfKey, FieldTypes::RESERVED_KEYS, true)) {
                    $this->fail("{$pfPath}.key", 'Invalid pivot field key.');
                }
                if (isset($seen[$pfKey])) {
                    $this->fail("{$pfPath}.key", "Duplicate pivot field key '{$pfKey}'.");
                }
                $seen[$pfKey] = true;

                $pfLabel = $pf['label'] ?? null;
                if (!is_string($pfLabel) || trim($pfLabel) === '' || mb_strlen($pfLabel) > 80) {
                    $this->fail("{$pfPath}.label", 'Every pivot field needs a label.');
                }
                $pfType = $pf['type'] ?? null;
                if (!in_array($pfType, FieldTypes::PIVOT_TYPES, true)) {
                    $this->fail("{$pfPath}.type", 'Pivot fields are scalar types only (text, number, price, boolean, select, date, sku).');
                }

                $cleanPf = [
                    'key' => $pfKey,
                    'label' => trim($pfLabel),
                    'type' => $pfType,
                    'required' => (bool) ($pf['required'] ?? false),
                ];
                if ($pfType === 'select') {
                    $cleanPf['options'] = $this->validateOptions($pf['options'] ?? null, $pfPath);
                }
                $clean[] = $cleanPf;
            }
            $out['pivot_fields'] = $clean;
        }

        return $out;
    }

    /** Allow-listed, scalar-valued per-type settings. Unknown keys dropped. */
    private function validateSettings(array $settings, string $path): array
    {
        $allowed = ['max_length', 'min', 'max', 'step', 'placeholder', 'help', 'accept', 'rows', 'pattern', 'pattern_message'];
        $clean = [];
        foreach ($settings as $k => $v) {
            if (!in_array($k, $allowed, true) || (!is_scalar($v) && $v !== null)) {
                continue;
            }
            if (is_string($v)) {
                $v = mb_substr($v, 0, 200);
            }
            $clean[$k] = $v;
        }

        // A pattern must compile now — a broken regex must never reach record
        // validation where it would reject every value with a cryptic error.
        if (isset($clean['pattern']) && is_string($clean['pattern']) && $clean['pattern'] !== '') {
            $delimited = '/' . str_replace('/', '\/', $clean['pattern']) . '/u';
            if (@preg_match($delimited, '') === false) {
                $this->fail("{$path}.settings.pattern", 'The pattern is not a valid regular expression.');
            }
        } else {
            unset($clean['pattern'], $clean['pattern_message']);
        }

        return $clean;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
