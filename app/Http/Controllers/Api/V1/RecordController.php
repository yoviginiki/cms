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

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => $this->serialize($r)),
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
     * Bulk publish/draft/delete. Deletes skip in-use records unless force=1;
     * the response reports what was skipped.
     */
    public function bulk(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $validated = $request->validate([
            'action' => ['required', 'in:publish,draft,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
            'force' => ['sometimes', 'boolean'],
        ]);

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
            } else {
                $status = $validated['action'] === 'publish' ? 'published' : 'draft';
                $this->service->save($collection, $site, $record, ['status' => $status]);
            }
            $done++;
        }

        return response()->json(['data' => ['done' => $done, 'skipped' => $skipped]]);
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
