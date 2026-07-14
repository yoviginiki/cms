<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Jobs\ExecuteCollectionImportJob;
use App\Domain\Collections\Services\SpreadsheetReader;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV/XLSX import into a collection (upload → mapping preview → queued
 * execute → polled status) and CSV export. Same cache-keyed progress contract
 * as the WordPress importer.
 */
class CollectionImportController extends Controller
{
    public function __construct(private SpreadsheetReader $reader)
    {
    }

    public function upload(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:51200'],
        ]);

        $file = $request->file('file');
        $importId = Str::uuid()->toString();
        $extension = mb_strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        $tempPath = storage_path("app/collection-imports/{$importId}.{$extension}");
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        copy($file->getRealPath(), $tempPath);

        // Headers + first 20 rows for the mapping UI.
        $headers = [];
        $preview = [];
        foreach ($this->reader->rows($tempPath) as $i => $row) {
            if ($i === 0) {
                $headers = $row;
                continue;
            }
            $preview[] = $row;
            if (count($preview) >= 20) {
                break;
            }
        }

        if ($headers === []) {
            @unlink($tempPath);

            return response()->json(['message' => 'The file appears to be empty.'], 422);
        }

        Cache::put("collection-import:{$importId}", [
            'status' => 'uploaded',
            'message' => 'File uploaded, ready for mapping.',
            'site_id' => $site->id,
            'collection_id' => $collection->id,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $tempPath,
            'uploaded_at' => now()->toISOString(),
        ], now()->addHours(2));

        return response()->json([
            'data' => [
                'import_id' => $importId,
                'filename' => $file->getClientOriginalName(),
                'headers' => $headers,
                'preview_rows' => $preview,
            ],
        ], 201);
    }

    public function execute(Request $request, Site $site, ContentCollection $collection, string $importId): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $meta = Cache::get("collection-import:{$importId}");
        if (!$meta || ($meta['collection_id'] ?? '') !== $collection->id) {
            return response()->json(['message' => 'Import not found.'], 404);
        }
        if (!file_exists($meta['file_path'] ?? '')) {
            return response()->json(['message' => 'Import file not found. Please re-upload.'], 404);
        }
        if (in_array($meta['status'] ?? '', ['queued', 'running', 'completed'], true)) {
            return response()->json(['message' => "Import is already {$meta['status']}."], 409);
        }

        $validated = $request->validate([
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['nullable', 'string', 'max:40'],
            'mode' => ['required', 'in:insert,upsert'],
            'key_field' => ['required_if:mode,upsert', 'nullable', 'string', 'max:40'],
            'error_policy' => ['sometimes', 'in:skip,halt'],
            'status' => ['sometimes', 'in:draft,published'],
            'create_missing_relations' => ['sometimes', 'boolean'],
        ]);

        if (($validated['mode'] ?? 'insert') === 'upsert') {
            $keyField = $collection->field($validated['key_field'] ?? '');
            if (!$keyField || !($keyField['unique'] ?? false)) {
                return response()->json(['message' => 'Update-by-key needs a unique field as the match key.'], 422);
            }
            if (!in_array($validated['key_field'], $validated['mapping'], true)) {
                return response()->json(['message' => 'The match key field must be mapped to a file column.'], 422);
            }
        }

        Cache::put("collection-import:{$importId}", array_merge($meta, [
            'status' => 'queued',
            'message' => 'Import queued, waiting for worker…',
            'step' => 'queued',
            'progress' => 0,
        ]), now()->addHours(2));

        ExecuteCollectionImportJob::dispatch(
            $site->id,
            $collection->id,
            $importId,
            $meta['file_path'],
            $validated,
            $site->tenant_id,
        );

        return response()->json([
            'data' => ['import_id' => $importId, 'status' => 'queued'],
        ], 202);
    }

    public function status(Site $site, ContentCollection $collection, string $importId): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection);

        $meta = Cache::get("collection-import:{$importId}");
        if (!$meta || ($meta['collection_id'] ?? '') !== $collection->id) {
            return response()->json(['message' => 'Import not found.'], 404);
        }

        return response()->json([
            'data' => [
                'import_id' => $importId,
                'status' => $meta['status'] ?? 'unknown',
                'message' => $meta['message'] ?? '',
                'step' => $meta['step'] ?? null,
                'progress' => $meta['progress'] ?? 0,
                'counts' => $meta['counts'] ?? null,
                'result' => $meta['result'] ?? null,
                'error' => $meta['error'] ?? null,
            ],
        ]);
    }

    /** Stream current records as CSV: slug, status, scalar fields, relations as |-joined target slugs. */
    public function export(Site $site, ContentCollection $collection): StreamedResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection);

        $fields = $collection->fields();
        $filename = "{$collection->slug}-export-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($collection, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['slug', 'status'], array_column($fields, 'key')));

            Record::where('collection_id', $collection->id)
                ->with('relationsOut.toRecord:id,slug')
                ->orderBy('created_at')
                ->chunkById(200, function ($records) use ($out, $fields) {
                    foreach ($records as $record) {
                        $row = [$record->slug, $record->status];
                        foreach ($fields as $field) {
                            $row[] = $this->cellValue($record, $field);
                        }
                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function cellValue(Record $record, array $field): string
    {
        if ($field['type'] === 'relation') {
            return $record->relationsOut
                ->where('relation_key', $field['key'])
                ->sortBy('position')
                ->map(fn ($edge) => $edge->toRecord?->slug)
                ->filter()
                ->implode('|');
        }

        $value = $record->data[$field['key']] ?? null;
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return implode('|', array_filter($value, 'is_scalar'));
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function assertOnSite(Site $site, ContentCollection $collection): void
    {
        abort_if($collection->site_id !== $site->id, 404);
    }
}
