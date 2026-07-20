<?php

namespace App\Domain\Collections\Services;

use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates Collections (the schema-bearing container). Record
 * operations live in RecordService.
 */
class CollectionService
{
    public function __construct(private CollectionSchemaValidator $schemaValidator)
    {
    }

    public function create(Site $site, array $input): ContentCollection
    {
        $attrs = $this->baseAttributes($site, $input, null);

        return ContentCollection::create($attrs + ['site_id' => $site->id]);
    }

    /**
     * @return array{collection: ContentCollection, warnings: array<int, string>}
     */
    public function update(ContentCollection $collection, Site $site, array $input): array
    {
        $attrs = $this->baseAttributes($site, $input, $collection);
        $warnings = $this->schemaChangeWarnings($collection, $attrs['schema']);

        $searchableBefore = $this->searchableKeys($collection->fields());
        $collection->update($attrs);
        $collection->refresh();

        // Searchable flags changed → the tsvector contents are stale for
        // every existing record; rebuild in the background.
        if ($this->searchableKeys($collection->fields()) !== $searchableBefore) {
            \App\Domain\Collections\Jobs\ReindexCollectionJob::dispatch($site->id, $collection->id, $site->tenant_id);
        }

        return ['collection' => $collection, 'warnings' => $warnings];
    }

    /** @return array<int, string> sorted searchable field keys */
    private function searchableKeys(array $fields): array
    {
        $keys = array_values(array_map(
            fn ($f) => $f['key'],
            array_filter($fields, fn ($f) => $f['searchable'] ?? false),
        ));
        sort($keys);

        return $keys;
    }

    /**
     * Collections other schemas on this site point at via relation fields —
     * used by delete protection alongside record counts.
     *
     * @return array<int, string> names of referencing collections
     */
    public function relationDependents(ContentCollection $collection): array
    {
        $names = [];
        $others = ContentCollection::where('site_id', $collection->site_id)
            ->where('id', '!=', $collection->id)
            ->get(['id', 'name', 'schema']);

        foreach ($others as $other) {
            foreach ($other->fields() as $field) {
                if ($field['type'] === 'relation' && ($field['relation']['collection_id'] ?? null) === $collection->id) {
                    $names[] = $other->name;
                    break;
                }
            }
        }

        return $names;
    }

    private function baseAttributes(Site $site, array $input, ?ContentCollection $existing): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'A collection needs a name (max 120 chars).']);
        }

        $tier = $input['tier'] ?? $existing?->tier ?? 'static';
        if (!in_array($tier, ContentCollection::TIERS, true)) {
            throw ValidationException::withMessages(['tier' => 'Tier is static or dynamic.']);
        }

        $icon = isset($input['icon']) && is_string($input['icon']) ? mb_substr(trim($input['icon']), 0, 60) : $existing?->icon;

        $slug = $this->uniqueSlug($site, (string) ($input['slug'] ?? ''), $name, $existing);

        $schema = $this->schemaValidator->validate(
            is_array($input['schema'] ?? null) ? $input['schema'] : [],
            $site,
            $existing,
        );

        $settings = $existing?->settings ?? [];
        if (isset($input['settings']) && is_array($input['settings'])) {
            foreach (['currency', 'list_columns', 'description'] as $key) {
                if (array_key_exists($key, $input['settings'])) {
                    $settings[$key] = $input['settings'][$key];
                }
            }
            if (array_key_exists('hierarchy_field', $input['settings'])) {
                $settings['hierarchy_field'] = $this->validateHierarchyField(
                    $input['settings']['hierarchy_field'],
                    $schema,
                    $existing,
                );
            }
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'icon' => $icon,
            'tier' => $tier,
            'schema' => $schema,
            'settings' => $settings,
        ];
    }

    /**
     * S3 hierarchy: the parent pointer must be a self-relation mode-one
     * field of this collection (which also means hierarchy can only be
     * enabled on update — self-relations need an existing collection id).
     */
    private function validateHierarchyField(mixed $key, array $schema, ?ContentCollection $existing): ?string
    {
        if ($key === null || $key === '') {
            return null; // explicit disable
        }
        if (!is_string($key) || !$existing) {
            throw ValidationException::withMessages([
                'settings.hierarchy_field' => 'Hierarchy needs an existing collection with a parent field.',
            ]);
        }
        foreach ($schema['fields'] as $field) {
            if ($field['key'] === $key) {
                $ok = $field['type'] === 'relation'
                    && ($field['relation']['mode'] ?? null) === 'one'
                    && ($field['relation']['collection_id'] ?? null) === $existing->id;
                if (!$ok) {
                    throw ValidationException::withMessages([
                        'settings.hierarchy_field' => "Field '{$key}' must be a relation to this same collection in 'one' mode.",
                    ]);
                }

                return $key;
            }
        }

        throw ValidationException::withMessages([
            'settings.hierarchy_field' => "Field '{$key}' does not exist in the schema.",
        ]);
    }

    private function uniqueSlug(Site $site, string $requested, string $name, ?ContentCollection $existing): string
    {
        $base = Slugify::slug($requested !== '' ? $requested : $name);
        if ($base === '') {
            throw ValidationException::withMessages(['slug' => 'Could not derive a slug — provide one.']);
        }

        $slug = $base;
        $n = 2;
        while (ContentCollection::where('site_id', $site->id)
            ->where('slug', $slug)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /**
     * Non-blocking heads-up when a schema edit orphans or retypes stored data.
     *
     * @return array<int, string>
     */
    private function schemaChangeWarnings(ContentCollection $collection, array $newSchema): array
    {
        $recordCount = Record::where('collection_id', $collection->id)->count();
        if ($recordCount === 0) {
            return [];
        }

        $old = collect($collection->fields())->keyBy('key');
        $new = collect($newSchema['fields'])->keyBy('key');
        $warnings = [];

        foreach ($old as $key => $field) {
            if (!$new->has($key)) {
                $warnings[] = "Field '{$field['label']}' was removed — its stored values on {$recordCount} records stay in place but are no longer shown or validated.";
            } elseif ($new[$key]['type'] !== $field['type']) {
                $warnings[] = "Field '{$field['label']}' changed type ({$field['type']} → {$new[$key]['type']}) — existing values may fail validation on next edit.";
            }
        }

        return $warnings;
    }
}
