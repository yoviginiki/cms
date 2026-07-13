<?php

namespace App\Domain\Collections\Jobs;

use App\Domain\Collections\FieldTypes;
use App\Domain\Collections\Services\RecordService;
use App\Domain\Collections\Services\SpreadsheetReader;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Queued CSV/XLSX import into a collection (Track G). Options:
 *  - mapping:        { columnIndex(string) => field_key } ('' = ignore column)
 *  - mode:           insert | upsert (update-by-key: match on key_field —
 *                    this is how supplier price lists refresh)
 *  - key_field:      unique field key used for upsert matching
 *  - error_policy:   skip (collect + continue) | halt (stop at first error)
 *  - status:         draft | published for created records
 *  - create_missing_relations: bool — unresolved relation values become new
 *                    draft records (title only) in the target collection
 *
 * Progress + result land in cache key collection-import:{importId}, polled by
 * the admin UI (same contract as the WordPress importer). Every record write
 * goes through RecordService — imports get validation, sanitization, unique
 * checks, references and staleness exactly like manual entry.
 */
class ExecuteCollectionImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    private const MAX_ERRORS_KEPT = 200;

    /** @var array<string, array<string, ?string>> relation field key => value => record id */
    private array $relationCache = [];

    public function __construct(
        public string $siteId,
        public string $collectionId,
        public string $importId,
        public string $filePath,
        public array $options,
        public string $tenantId,
    ) {
    }

    public function handle(RecordService $records, SpreadsheetReader $reader): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        try {
            $site = Site::findOrFail($this->siteId);
            $collection = ContentCollection::where('site_id', $site->id)->findOrFail($this->collectionId);

            $this->updateStatus(['status' => 'running', 'step' => 'counting', 'message' => 'Counting rows…', 'progress' => 1]);
            $total = $reader->countRows($this->filePath);

            $mapping = $this->fieldMapping($collection);
            $mode = $this->options['mode'] ?? 'insert';
            $keyField = $this->options['key_field'] ?? null;
            $haltOnError = ($this->options['error_policy'] ?? 'skip') === 'halt';
            $status = $this->options['status'] ?? 'draft';
            if (!in_array($status, Record::STATUSES, true)) {
                $status = 'draft';
            }

            $created = 0;
            $updated = 0;
            $failed = 0;
            $errors = [];
            $rowNumber = 0;

            foreach ($reader->rows($this->filePath) as $cells) {
                $rowNumber++;
                if ($rowNumber === 1) {
                    continue; // header row
                }
                if (implode('', $cells) === '') {
                    continue; // blank line
                }

                try {
                    $input = $this->rowToInput($collection, $mapping, $cells, $status, $site);

                    $existing = null;
                    if ($mode === 'upsert' && $keyField) {
                        $existing = $this->findByKey($collection, $keyField, $input['data'][$keyField] ?? null);
                    }
                    if ($existing) {
                        // Update-by-key keeps the record's current status.
                        unset($input['status']);
                    }

                    $records->save($collection, $site, $existing, $input);
                    $existing ? $updated++ : $created++;
                } catch (ValidationException $e) {
                    $failed++;
                    if (count($errors) < self::MAX_ERRORS_KEPT) {
                        $errors[] = ['row' => $rowNumber, 'message' => implode(' ', collect($e->errors())->flatten()->all())];
                    }
                    if ($haltOnError) {
                        $this->finish('failed', "Stopped at row {$rowNumber} (halt-on-error).", $total, $created, $updated, $failed, $errors);

                        return;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    if (count($errors) < self::MAX_ERRORS_KEPT) {
                        $errors[] = ['row' => $rowNumber, 'message' => 'Unexpected error: ' . $e->getMessage()];
                    }
                    Log::warning('Collection import row failed', ['import' => $this->importId, 'row' => $rowNumber, 'error' => $e->getMessage()]);
                    if ($haltOnError) {
                        $this->finish('failed', "Stopped at row {$rowNumber} (halt-on-error).", $total, $created, $updated, $failed, $errors);

                        return;
                    }
                }

                if (($rowNumber - 1) % 25 === 0) {
                    $done = $rowNumber - 1;
                    $this->updateStatus([
                        'status' => 'running',
                        'step' => 'importing',
                        'message' => "Importing… {$done}/{$total} rows",
                        'progress' => $total > 0 ? min(99, (int) round($done / $total * 100)) : 50,
                        'counts' => ['created' => $created, 'updated' => $updated, 'failed' => $failed],
                    ]);
                }
            }

            $this->finish('completed', 'Import finished.', $total, $created, $updated, $failed, $errors);
        } catch (\Throwable $e) {
            Log::error('Collection import failed', ['import' => $this->importId, 'error' => $e->getMessage()]);
            $this->updateStatus([
                'status' => 'failed',
                'message' => 'Import failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($this->filePath);
        }
    }

    /** @return array<int, array{index: int, field: array}> column index => schema field */
    private function fieldMapping(ContentCollection $collection): array
    {
        $mapping = [];
        foreach (($this->options['mapping'] ?? []) as $columnIndex => $fieldKey) {
            if (!is_string($fieldKey) || $fieldKey === '') {
                continue;
            }
            $field = $collection->field($fieldKey);
            if ($field) {
                $mapping[] = ['index' => (int) $columnIndex, 'field' => $field];
            }
        }

        return $mapping;
    }

    /**
     * @return array{data: array, relations: array, status: string}
     */
    private function rowToInput(ContentCollection $collection, array $mapping, array $cells, string $status, Site $site): array
    {
        $data = [];
        $relations = [];

        foreach ($mapping as $map) {
            $field = $map['field'];
            $value = $cells[$map['index']] ?? '';
            if ($value === '') {
                continue;
            }

            if ($field['type'] === 'relation') {
                $relations[$field['key']] = $this->resolveRelationCell($field, $value, $collection, $site);
            } elseif (in_array($field['type'], ['multi_select', 'gallery'], true)) {
                $data[$field['key']] = array_values(array_filter(array_map('trim', explode('|', $value)), fn ($v) => $v !== ''));
            } else {
                $data[$field['key']] = $value;
            }
        }

        return ['data' => $data, 'relations' => $relations, 'status' => $status];
    }

    /**
     * Resolve a relation cell ("Isaac Asimov" or "isaac-asimov|ray-bradbury")
     * against the target collection by slug or title (case-insensitive),
     * optionally creating missing targets as draft records.
     *
     * @return array<int, array{id: string}>
     */
    private function resolveRelationCell(array $field, string $value, ContentCollection $collection, Site $site): array
    {
        $targetCollectionId = $field['relation']['collection_id'];
        $values = $field['relation']['mode'] === 'many'
            ? array_values(array_filter(array_map('trim', explode('|', $value)), fn ($v) => $v !== ''))
            : [trim($value)];

        $entries = [];
        foreach ($values as $needle) {
            $id = $this->resolveRelationValue($field['key'], $targetCollectionId, $needle, $site);
            if ($id === null) {
                throw ValidationException::withMessages([
                    "relations.{$field['key']}" => "{$field['label']}: '{$needle}' not found in the target collection.",
                ]);
            }
            $entries[] = ['id' => $id];
        }

        return $entries;
    }

    private function resolveRelationValue(string $fieldKey, string $targetCollectionId, string $needle, Site $site): ?string
    {
        $cacheKey = mb_strtolower($needle);
        if (array_key_exists($cacheKey, $this->relationCache[$fieldKey] ?? [])) {
            return $this->relationCache[$fieldKey][$cacheKey];
        }

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $safe = str_replace(['%', '_'], ['\%', '\_'], $needle);

        $match = Record::where('collection_id', $targetCollectionId)
            ->where(fn ($q) => $q->where('slug', $needle)->orWhere('title', $like, $safe))
            ->value('id');

        if (!$match && ($this->options['create_missing_relations'] ?? false)) {
            $target = ContentCollection::find($targetCollectionId);
            if ($target && $target->titleField()) {
                $created = app(RecordService::class)->save($target, $site, null, [
                    'data' => [$target->titleField() => $needle],
                    'status' => 'draft',
                ]);
                $match = $created->id;
            }
        }

        return $this->relationCache[$fieldKey][$cacheKey] = $match ?: null;
    }

    private function findByKey(ContentCollection $collection, string $keyField, mixed $rawValue): ?Record
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $field = $collection->field($keyField);
        $value = $field && $field['type'] === 'sku' && is_string($rawValue)
            ? FieldTypes::normalizeSku($rawValue)
            : $rawValue;

        return Record::where('collection_id', $collection->id)->whereField($keyField, $value)->first();
    }

    private function finish(string $status, string $message, int $total, int $created, int $updated, int $failed, array $errors): void
    {
        $this->updateStatus([
            'status' => $status,
            'step' => 'done',
            'message' => $message,
            'progress' => 100,
            'counts' => ['created' => $created, 'updated' => $updated, 'failed' => $failed, 'total' => $total],
            'result' => ['created' => $created, 'updated' => $updated, 'failed' => $failed, 'total' => $total, 'errors' => $errors],
        ]);
    }

    private function updateStatus(array $patch): void
    {
        $key = "collection-import:{$this->importId}";
        Cache::put($key, array_merge(Cache::get($key, []), $patch), now()->addHours(2));
    }
}
