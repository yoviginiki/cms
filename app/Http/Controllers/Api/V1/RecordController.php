<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Services\RecordService;
use App\Domain\References\Services\ReferenceUsageService;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Record CRUD inside one collection: schema-driven list (configurable
 * columns/sort live client-side off show_in_list), quick search, bulk ops.
 * All writes go through RecordService — never straight to the model.
 */
class RecordController extends Controller
{
    private const SORTABLE = ['title', 'slug', 'status', 'position', 'created_at', 'updated_at', 'published_at'];

    public function __construct(
        private RecordService $service,
        private ReferenceUsageService $usage,
    ) {
    }

    public function index(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection);

        $query = Record::where('collection_id', $collection->id);

        if (($status = $request->input('status')) && in_array($status, Record::STATUSES, true)) {
            $query->where('status', $status);
        }

        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($w) => $w->where('title', $like, $safe)->orWhere('slug', $like, $safe));
        }

        [$sort, $direction] = $this->sort($request, $collection);
        if (str_starts_with($sort, 'data.')) {
            $key = substr($sort, 5);
            $field = $collection->field($key);
            // jsonb (->) comparison sorts numbers numerically; text (->>) for the rest.
            $accessor = in_array($field['type'] ?? '', ['number', 'price'], true) ? "data->'{$key}'" : "data->>'{$key}'";
            $query->orderByRaw("{$accessor} {$direction} NULLS LAST");
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('created_at', 'desc');

        $perPage = min(100, max(5, (int) $request->input('per_page', 25)));
        $page = $query->paginate($perPage);

        // Hierarchy (S3): ship each row's parent id so the admin list can
        // render the tree (one bulk query, not N).
        $parents = [];
        if ($hierarchyKey = $collection->hierarchyField()) {
            $parents = \App\Models\RecordRelation::whereIn('from_record_id', collect($page->items())->pluck('id'))
                ->where('relation_key', $hierarchyKey)
                ->pluck('to_record_id', 'from_record_id')
                ->all();
        }

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => $this->serialize($r) + ['parent_id' => $parents[$r->id] ?? null]),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $record = $this->service->save($collection, $site, null, $request->all());

        return response()->json(['data' => $this->serialize($record, withRelations: true)], 201);
    }

    public function show(Site $site, ContentCollection $collection, Record $record): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection, $record);

        return response()->json(['data' => $this->serialize($record, withRelations: true)]);
    }

    public function update(Request $request, Site $site, ContentCollection $collection, Record $record): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $record);

        $record = $this->service->save($collection, $site, $record, $request->all());

        return response()->json(['data' => $this->serialize($record, withRelations: true)]);
    }

    public function destroy(Request $request, Site $site, ContentCollection $collection, Record $record): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $record);

        $usage = $this->usage->usage($site, 'record', $record->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Record '{$record->title}' is still in use. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $this->service->delete($record, $site);

        return response()->json(['message' => 'Record deleted.']);
    }

    /**
     * Bulk publish/draft/delete/set_field. Deletes skip in-use records unless
     * force=1; the response reports what was skipped. set_field writes one
     * field value onto every selected record (validation errors skip the row).
     */
    public function bulk(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $validated = $request->validate([
            'action' => ['required', 'in:publish,draft,delete,set_field'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
            'force' => ['sometimes', 'boolean'],
            'field' => ['required_if:action,set_field', 'string', 'max:40'],
            'value' => ['sometimes', 'nullable'],
        ]);

        $field = null;
        if ($validated['action'] === 'set_field') {
            $field = $collection->field($validated['field'] ?? '');
            if (!$field || in_array($field['type'], ['relation'], true)) {
                return response()->json(['message' => 'Pick a non-relation schema field to bulk-edit.'], 422);
            }
        }

        $records = Record::where('collection_id', $collection->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $done = 0;
        $skipped = [];

        foreach ($records as $record) {
            if ($validated['action'] === 'delete') {
                $usage = $this->usage->usage($site, 'record', $record->id);
                if ($usage['count'] > 0 && !$request->boolean('force')) {
                    $skipped[] = ['id' => $record->id, 'title' => $record->title, 'usedOnCount' => $usage['count']];
                    continue;
                }
                $this->service->delete($record, $site);
            } elseif ($validated['action'] === 'set_field') {
                $data = $record->data ?? [];
                $value = $request->input('value');
                if ($value === null || $value === '') {
                    unset($data[$field['key']]);
                } else {
                    $data[$field['key']] = $value;
                }
                try {
                    $this->service->save($collection, $site, $record, ['data' => $data]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $skipped[] = ['id' => $record->id, 'title' => $record->title, 'error' => collect($e->errors())->flatten()->first()];
                    continue;
                }
            } else {
                $status = $validated['action'] === 'publish' ? 'published' : 'draft';
                $this->service->save($collection, $site, $record, ['status' => $status]);
            }
            $done++;
        }

        return response()->json(['data' => ['done' => $done, 'skipped' => $skipped]]);
    }

    /** Copy a record (data + relations) as a new draft with a "(copy)" title. */
    public function duplicate(Site $site, ContentCollection $collection, Record $record): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $record);

        $relations = [];
        foreach ($record->relationsOut()->orderBy('position')->get(['relation_key', 'to_record_id', 'pivot']) as $edge) {
            $relations[$edge->relation_key][] = array_filter([
                'id' => $edge->to_record_id,
                'pivot' => $edge->pivot ?: null,
            ]);
        }

        $data = $record->data ?? [];
        $titleField = $collection->titleField();
        if ($titleField && is_string($data[$titleField] ?? null)) {
            $data[$titleField] = mb_substr($data[$titleField] . ' (copy)', 0, 500);
        }

        $copy = $this->service->save($collection, $site, null, [
            'data' => $data,
            'relations' => $relations,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $this->serialize($copy, withRelations: true)], 201);
    }

    /** Revision history, newest first. */
    public function revisions(Site $site, ContentCollection $collection, Record $record): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection, $record);

        $items = \App\Models\RecordRevision::where('record_id', $record->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(\App\Domain\Collections\Services\RecordRevisionService::KEEP)
            ->get()
            ->map(fn ($rev) => [
                'id' => $rev->id,
                'event' => $rev->event,
                'title' => $rev->title,
                'status' => $rev->status,
                'data' => $rev->data ?: (object) [],
                'relations' => $rev->relations ?: (object) [],
                'user' => $rev->user?->name,
                'created_at' => $rev->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $items]);
    }

    /** Restore a record to a stored revision (writes a 'restored' snapshot). */
    public function restoreRevision(Site $site, ContentCollection $collection, Record $record, string $revisionId): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection, $record);

        $revision = \App\Models\RecordRevision::where('record_id', $record->id)->findOrFail($revisionId);

        $input = app(\App\Domain\Collections\Services\RecordRevisionService::class)->restoreInput($revision);
        $input['__revision_event'] = 'restored';
        $record = $this->service->save($collection, $site, $record, $input);

        return response()->json(['data' => $this->serialize($record, withRelations: true)]);
    }

    private function sort(Request $request, ContentCollection $collection): array
    {
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sort = (string) $request->input('sort', 'created_at');

        if (in_array($sort, self::SORTABLE, true)) {
            return [$sort, $sort === 'created_at' && !$request->has('direction') ? 'desc' : $direction];
        }

        // data.{key} — only for keys that exist in the schema (the key is
        // interpolated into SQL, so it must come from the validated schema,
        // never the request).
        if (str_starts_with($sort, 'data.')) {
            $field = $collection->field(substr($sort, 5));
            if ($field && !in_array($field['type'], ['relation', 'gallery', 'rich_text'], true)) {
                return ["data.{$field['key']}", $direction];
            }
        }

        return ['created_at', 'desc'];
    }

    private function serialize(Record $record, bool $withRelations = false): array
    {
        $out = [
            'id' => $record->id,
            'collection_id' => $record->collection_id,
            'slug' => $record->slug,
            'title' => $record->title,
            'status' => $record->status,
            'position' => $record->position,
            'data' => $record->data ?: (object) [],
            'published_at' => $record->published_at?->toISOString(),
            'publish_at' => $record->publish_at?->toISOString(),
            'unpublish_at' => $record->unpublish_at?->toISOString(),
            'seo_meta' => $record->seo_meta ?: (object) [],
            'created_at' => $record->created_at?->toISOString(),
            'updated_at' => $record->updated_at?->toISOString(),
        ];

        if ($withRelations) {
            $grouped = [];
            $edges = $record->relationsOut()->with('toRecord:id,title,slug,status')->orderBy('position')->get();
            foreach ($edges as $edge) {
                $grouped[$edge->relation_key][] = [
                    'id' => $edge->to_record_id,
                    'title' => $edge->toRecord?->title,
                    'slug' => $edge->toRecord?->slug,
                    'status' => $edge->toRecord?->status,
                    'pivot' => $edge->pivot ?: (object) [],
                    'position' => $edge->position,
                ];
            }
            $out['relations'] = $grouped === [] ? (object) [] : $grouped;
        }

        return $out;
    }

    private function assertOnSite(Site $site, ContentCollection $collection, ?Record $record = null): void
    {
        abort_if($collection->site_id !== $site->id, 404);
        abort_if($record && $record->collection_id !== $collection->id, 404);
    }
}
