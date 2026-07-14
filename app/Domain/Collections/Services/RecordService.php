<?php

namespace App\Domain\Collections\Services;

use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\StalenessResolver;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\RecordRelation;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for Records: schema-driven validation + sanitization
 * (RecordDataProcessor), service-level unique checks, relation sync with pivot
 * data, tsvector maintenance, entity_references edges (record → asset,
 * record → record) and staleness flags — all in one transaction.
 */
class RecordService
{
    public function __construct(
        private RecordDataProcessor $processor,
        private ReferenceRecorder $references,
        private StalenessResolver $staleness,
    ) {
    }

    /**
     * Create or update a record.
     *
     * @param array{data?: array, relations?: array, slug?: ?string, status?: string} $input
     */
    public function save(ContentCollection $collection, Site $site, ?Record $record, array $input): Record
    {
        $fields = $collection->fields();
        if ($fields === [] || !$collection->titleField()) {
            throw ValidationException::withMessages([
                'data' => 'This collection has no fields yet — design its schema before adding records.',
            ]);
        }

        // `data` is a full replacement when present; when absent (partial
        // updates like bulk status changes) the stored data stays untouched.
        $hasData = is_array($input['data'] ?? null);
        if (!$hasData && !$record) {
            $hasData = true;
            $input['data'] = [];
        }
        $data = $hasData
            ? $this->processor->processFields($fields, $input['data'])
            : ($record->data ?? []);

        $title = $data[$collection->titleField()] ?? null;
        if (!is_string($title) || $title === '') {
            throw ValidationException::withMessages([
                'data.' . $collection->titleField() => 'The title field is required.',
            ]);
        }

        if ($hasData) {
            $this->assertUniqueFields($collection, $fields, $data, $record);
        }

        $relations = $this->processRelations($collection, $fields, is_array($input['relations'] ?? null) ? $input['relations'] : []);

        $status = $input['status'] ?? $record?->status ?? 'draft';
        if (!in_array($status, Record::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Status is draft or published.']);
        }

        // Slugs stay stable on update unless explicitly changed.
        $requestedSlug = (string) ($input['slug'] ?? '');
        if ($record && $requestedSlug === '') {
            $slug = $record->slug;
        } else {
            $slug = $this->uniqueSlug(
                $collection,
                $requestedSlug,
                (string) ($data[$collection->slugSource()] ?? $title),
                $record,
            );
        }

        return DB::transaction(function () use ($collection, $site, $record, $data, $relations, $status, $slug, $title, $input) {
            $attrs = [
                'slug' => $slug,
                'title' => mb_substr($title, 0, 255),
                'status' => $status,
                'data' => $data,
            ];

            if ($status === 'published' && (!$record || $record->status !== 'published')) {
                $attrs['published_at'] = now();
            }

            // Delta-publish flag: this record's own static page (and its
            // collection's archive/index) needs rebuilding. Unpublishing
            // flags too — the page must be removed/refreshed.
            $attrs['needs_republish'] = true;
            $attrs['needs_republish_reason'] = $record ? 'record_updated' : 'record_created';

            if ($record) {
                $record->update($attrs);
            } else {
                $record = Record::create($attrs + [
                    'collection_id' => $collection->id,
                    'site_id' => $site->id,
                    'position' => (int) ($input['position'] ?? 0),
                ]);
            }

            // Relations payload is authoritative for every key it contains;
            // keys not present are left untouched (partial updates stay safe).
            $pivotSearchStrings = $this->syncRelations($record, $site, $relations);

            $record->updateSearchText($this->searchStrings($collection, $record, $pivotSearchStrings));

            $this->references->persistEdges($site->id, 'record', $record->id, $this->edges($collection, $record));

            $this->staleness->markStale($site, 'record', $record->id, 'record_updated');
            $this->staleness->markStale($site, 'collection', $collection->id, 'collection_records_changed');

            return $record->refresh();
        });
    }

    public function delete(Record $record, Site $site): void
    {
        DB::transaction(function () use ($record, $site) {
            // Flag referrers before the edges/rows disappear.
            $this->staleness->markStale($site, 'record', $record->id, 'record_deleted');
            $this->staleness->markStale($site, 'collection', $record->collection_id, 'collection_records_changed');
            $this->references->persistEdges($site->id, 'record', $record->id, []);
            $record->delete(); // record_relations cascade via FK
        });
    }

    /**
     * Pure edge computation for a stored record (references:backfill).
     *
     * @return array<int, array{target_type: string, target_id: ?string, kind: string}>
     */
    public function computeEdges(Record $record): array
    {
        $collection = $record->collection ?? ContentCollection::withoutGlobalScopes()->find($record->collection_id);
        if (!$collection) {
            return [];
        }

        return $this->edges($collection, $record);
    }

    /** Service-level uniqueness for `unique`-flagged fields (per collection). */
    private function assertUniqueFields(ContentCollection $collection, array $fields, array $data, ?Record $record): void
    {
        $errors = [];

        foreach ($fields as $field) {
            if (!($field['unique'] ?? false) || !array_key_exists($field['key'], $data)) {
                continue;
            }
            $key = $field['key'];
            $exists = Record::where('collection_id', $collection->id)
                ->whereField($key, $data[$key])
                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                ->exists();
            if ($exists) {
                $errors["data.{$key}"] = "{$field['label']} '{$data[$key]}' is already used by another record.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate the relations payload: targets exist in the field's target
     * collection, mode-one keeps a single edge, pivot data passes the
     * pivot_fields schema.
     *
     * @return array<string, array<int, array{id: string, pivot: array}>>
     */
    private function processRelations(ContentCollection $collection, array $fields, array $payload): array
    {
        $out = [];
        $errors = [];

        foreach ($fields as $field) {
            if ($field['type'] !== 'relation' || !array_key_exists($field['key'], $payload)) {
                continue;
            }

            $key = $field['key'];
            $entries = $payload[$key];
            if (!is_array($entries)) {
                $errors["relations.{$key}"] = "{$field['label']}: invalid relation payload.";
                continue;
            }

            $mode = $field['relation']['mode'];
            if ($mode === 'one' && count($entries) > 1) {
                $errors["relations.{$key}"] = "{$field['label']} links a single record.";
                continue;
            }
            if (($field['required'] ?? false) && $entries === []) {
                $errors["relations.{$key}"] = "{$field['label']} is required.";
                continue;
            }
            if (count($entries) > 200) {
                $errors["relations.{$key}"] = "{$field['label']}: at most 200 linked records.";
                continue;
            }

            $targetCollectionId = $field['relation']['collection_id'];
            $ids = [];
            $normalized = [];
            foreach (array_values($entries) as $i => $entry) {
                $id = is_array($entry) ? ($entry['id'] ?? null) : (is_string($entry) ? $entry : null);
                if (!is_string($id)) {
                    $errors["relations.{$key}.{$i}"] = "{$field['label']}: invalid linked record.";
                    continue 2;
                }
                if (in_array($id, $ids, true)) {
                    continue; // silently dedupe
                }
                $ids[] = $id;

                $pivot = [];
                $pivotFields = $field['relation']['pivot_fields'] ?? [];
                if ($pivotFields !== []) {
                    $rawPivot = is_array($entry) && is_array($entry['pivot'] ?? null) ? $entry['pivot'] : [];
                    $pivot = $this->processor->processFields($pivotFields, $rawPivot, "relations.{$key}.{$i}");
                }

                $normalized[] = ['id' => $id, 'pivot' => $pivot];
            }

            if ($ids !== []) {
                $found = Record::where('collection_id', $targetCollectionId)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->all();
                $missing = array_diff($ids, $found);
                if ($missing !== []) {
                    $errors["relations.{$key}"] = "{$field['label']}: some linked records no longer exist.";
                    continue;
                }
            }

            $out[$key] = $normalized;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * Replace the record's edges for each relation key present in the payload.
     * Returns pivot text/sku values for the search index (supplier part
     * numbers must be findable from the parent record).
     *
     * @return array<int, string>
     */
    private function syncRelations(Record $record, Site $site, array $relations): array
    {
        $searchStrings = [];

        foreach ($relations as $key => $entries) {
            RecordRelation::where('from_record_id', $record->id)
                ->where('relation_key', $key)
                ->delete();

            foreach (array_values($entries) as $position => $entry) {
                RecordRelation::create([
                    'site_id' => $site->id,
                    'from_record_id' => $record->id,
                    'to_record_id' => $entry['id'],
                    'relation_key' => $key,
                    'pivot' => $entry['pivot'],
                    'position' => $position,
                ]);
            }
        }

        // Collect pivot text/sku values across ALL current relations (not just
        // the keys synced now) so search_text stays complete.
        foreach ($record->relationsOut()->get() as $edge) {
            foreach ($edge->pivot ?? [] as $value) {
                if (is_string($value) && $value !== '') {
                    $searchStrings[] = $value;
                }
            }
        }

        return $searchStrings;
    }

    /** @return array<int, string> strings feeding the tsvector */
    private function searchStrings(ContentCollection $collection, Record $record, array $pivotStrings): array
    {
        $strings = [(string) $record->title, $record->slug];

        foreach ($collection->fields() as $field) {
            if (!($field['searchable'] ?? false)) {
                continue;
            }
            if ($field['type'] === 'relation') {
                // Related record titles — "search by author" on a book.
                foreach ($record->relationsOut()->where('relation_key', $field['key'])->with('toRecord:id,title')->get() as $edge) {
                    if ($edge->toRecord?->title) {
                        $strings[] = $edge->toRecord->title;
                    }
                }
                continue;
            }
            $value = $record->data[$field['key']] ?? null;
            if ($value === null) {
                continue;
            }
            if ($field['type'] === 'rich_text') {
                $strings[] = strip_tags((string) $value);
            } elseif (is_array($value)) {
                $strings[] = implode(' ', array_filter($value, 'is_scalar'));
            } elseif (is_scalar($value)) {
                $strings[] = (string) $value;
            }
        }

        return array_merge($strings, $pivotStrings);
    }

    /**
     * entity_references edges for this record: asset fields → uses_asset,
     * relation edges → record embeds (so a related record's change cascades
     * staleness to pages showing this one).
     *
     * @return array<int, array{target_type: string, target_id: ?string, kind: string}>
     */
    private function edges(ContentCollection $collection, Record $record): array
    {
        $edges = [];

        foreach ($collection->fields() as $field) {
            $value = $record->data[$field['key']] ?? null;
            if ($value === null) {
                continue;
            }
            if (in_array($field['type'], ['image', 'file'], true)) {
                $edges["asset|{$value}"] = ['target_type' => 'asset', 'target_id' => $value, 'kind' => 'uses_asset'];
            } elseif ($field['type'] === 'gallery' && is_array($value)) {
                foreach ($value as $assetId) {
                    $edges["asset|{$assetId}"] = ['target_type' => 'asset', 'target_id' => $assetId, 'kind' => 'uses_asset'];
                }
            }
        }

        foreach ($record->relationsOut()->get(['to_record_id']) as $edge) {
            $edges["record|{$edge->to_record_id}"] = ['target_type' => 'record', 'target_id' => $edge->to_record_id, 'kind' => 'embeds'];
        }

        return array_values($edges);
    }

    private function uniqueSlug(ContentCollection $collection, string $requested, string $source, ?Record $record): string
    {
        $base = Slugify::slug($requested !== '' ? $requested : $source);
        if ($base === '') {
            $base = 'record';
        }

        $slug = $base;
        $n = 2;
        while (Record::where('collection_id', $collection->id)
            ->where('slug', $slug)
            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
            ->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
