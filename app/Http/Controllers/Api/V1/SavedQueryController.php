<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Queries\QueryRunner;
use App\Domain\Collections\Queries\QuerySentence;
use App\Domain\Collections\Queries\SavedQueryValidator;
use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\SavedQuery;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Track G-Q — Saved Queries CRUD + live preview. Authoring is admin/owner
 * only (route middleware role:admin). Queries register `query → collection`
 * edges so record changes cascade staleness to every page embedding them.
 */
class SavedQueryController extends Controller
{
    public function __construct(
        private SavedQueryValidator $validator,
        private QueryRunner $runner,
        private QuerySentence $sentence,
        private ReferenceRecorder $references,
        private ReferenceUsageService $usage,
        private StalenessResolver $staleness,
    ) {
    }

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $queries = SavedQuery::where('site_id', $site->id)->orderBy('name')->get();
        $collections = ContentCollection::where('site_id', $site->id)->pluck('name', 'id');

        return response()->json(['data' => $queries->map(fn ($q) => $this->serialize($q, $collections[$q->definition['collection_id'] ?? ''] ?? null))]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $attrs = $this->validated($request, $site, null);
        $attrs['site_id'] = $site->id;
        $attrs['created_by'] = $request->user()?->id;

        $query = SavedQuery::create($attrs);
        $this->recordEdges($site, $query);

        return response()->json(['data' => $this->serialize($query)], 201);
    }

    public function show(Site $site, SavedQuery $savedQuery): JsonResponse
    {
        $this->authorize('view', $site);
        abort_if($savedQuery->site_id !== $site->id, 404);

        return response()->json(['data' => $this->serialize($savedQuery)]);
    }

    public function update(Request $request, Site $site, SavedQuery $savedQuery): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($savedQuery->site_id !== $site->id, 404);

        $savedQuery->update($this->validated($request, $site, $savedQuery));
        $this->recordEdges($site, $savedQuery);

        // Pages rendering this query show stale numbers now.
        $this->staleness->markStale($site, 'query', $savedQuery->id, 'query_updated');

        return response()->json(['data' => $this->serialize($savedQuery->refresh())]);
    }

    public function destroy(Request $request, Site $site, SavedQuery $savedQuery): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($savedQuery->site_id !== $site->id, 404);

        $usage = $this->usage->usage($site, 'query', $savedQuery->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Query '{$savedQuery->name}' is still in use. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $this->staleness->markStale($site, 'query', $savedQuery->id, 'query_deleted');
        $this->references->persistEdges($site->id, 'query', $savedQuery->id, []);
        $savedQuery->delete();

        return response()->json(['message' => 'Query deleted.']);
    }

    /**
     * Live preview while building: validated definition → sentence + sample
     * rows (never persisted).
     */
    public function preview(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        if ($request->input('mode') === 'sql') {
            return $this->previewSql($request, $site);
        }

        $definition = $this->validator->validate(
            is_array($request->input('definition')) ? $request->input('definition') : [],
            $site,
        );
        $collection = ContentCollection::findOrFail($definition['collection_id']);

        $preview = $definition;
        $preview['limit'] = min($preview['limit'], 10);
        // Param placeholders get their declared defaults (or neutral samples) for preview.
        $params = [];
        foreach ((array) $request->input('public_params', []) as $param) {
            if (is_array($param) && isset($param['key'])) {
                $params[$param['key']] = $param['default'] ?? ($param['type'] ?? 'text') === 'number' ? 0 : '';
            }
        }

        $result = app(\App\Domain\Collections\Queries\SimpleQueryCompiler::class)->run($collection, $preview, $params);

        return response()->json(['data' => [
            'sentence' => $this->sentence->describe($definition, $collection),
            'as_sql' => app(\App\Domain\Collections\Queries\SimpleToSql::class)->render($definition, $collection),
            'result' => $this->serializeResult($result),
        ]]);
    }

    /** SQL-mode live preview: guard + run the statement under the restricted role. */
    private function previewSql(Request $request, Site $site): JsonResponse
    {
        $sql = trim((string) $request->input('sql', ''));
        try {
            $result = app(\App\Domain\Collections\Queries\SqlQueryRunner::class)->run($site, $sql);
        } catch (\App\Domain\Collections\Queries\SqlGuardException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['sql' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => ['result' => $result]]);
    }

    /**
     * The mode bridge: the exact SQL a Simple-mode definition compiles to,
     * copyable into Advanced mode. Deliberately the primary SQL-learning path.
     */
    public function showSql(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $definition = $this->validator->validate(
            is_array($request->input('definition')) ? $request->input('definition') : [],
            $site,
        );
        $collection = ContentCollection::findOrFail($definition['collection_id']);

        return response()->json(['data' => [
            'sql' => app(\App\Domain\Collections\Queries\SimpleToSql::class)->render($definition, $collection),
        ]]);
    }

    private function validated(Request $request, Site $site, ?SavedQuery $existing): array
    {
        $name = trim((string) $request->input('name', $existing?->name ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'A query needs a name (max 120 chars).']);
        }

        $mode = $request->input('mode', $existing?->mode ?? 'simple');
        if (!in_array($mode, SavedQuery::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Query mode is simple or sql.']);
        }

        $definition = [];
        $sql = $existing?->sql;
        if ($mode === 'simple') {
            $definition = $this->validator->validate(
                is_array($request->input('definition')) ? $request->input('definition') : ($existing?->definition ?? []),
                $site,
                $existing,
            );
        } else {
            // SQL mode is admin/owner only (enforced by the route) — validate
            // the statement through the same guard the runner uses.
            $sql = trim((string) $request->input('sql', $existing?->sql ?? ''));
            if ($sql === '') {
                throw ValidationException::withMessages(['sql' => 'Write a SELECT statement.']);
            }
            try {
                app(\App\Domain\Collections\Queries\SqlQueryGuard::class)
                    ->validate($sql, app(\App\Domain\Collections\Queries\ScopedViewManager::class)->viewNames($site));
            } catch (\App\Domain\Collections\Queries\SqlGuardException $e) {
                throw ValidationException::withMessages(['sql' => $e->getMessage()]);
            }
        }

        $publicParams = [];
        foreach ((array) $request->input('public_params', $existing?->public_params ?? []) as $i => $param) {
            $key = $param['key'] ?? null;
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
                throw ValidationException::withMessages(["public_params.{$i}.key" => 'Invalid parameter key.']);
            }
            $type = $param['type'] ?? 'text';
            if (!in_array($type, SavedQuery::PARAM_TYPES, true)) {
                throw ValidationException::withMessages(["public_params.{$i}.type" => 'Invalid parameter type.']);
            }
            $publicParams[] = [
                'key' => $key,
                'type' => $type,
                'required' => (bool) ($param['required'] ?? false),
                'default' => is_scalar($param['default'] ?? null) ? $param['default'] : null,
            ];
        }

        $slug = $existing?->slug;
        if (!$slug) {
            $base = Slugify::slug($name) ?: 'query';
            $slug = $base;
            $n = 2;
            while (SavedQuery::where('site_id', $site->id)->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$n}";
                $n++;
            }
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'mode' => $mode,
            'definition' => $definition,
            'sql' => $mode === 'sql' ? $sql : null,
            'public_params' => $publicParams,
            'is_public' => (bool) $request->input('is_public', $existing?->is_public ?? false),
            'settings' => is_array($request->input('settings')) ? $request->input('settings') : ($existing?->settings ?? []),
        ];
    }

    /**
     * query → collection `lists`/`reads` edges so record changes cascade to
     * embedding pages. Simple mode reads definition.collection_id; SQL mode
     * maps its referenced col_/rel_ views back to collections.
     */
    private function recordEdges(Site $site, SavedQuery $query): void
    {
        $edges = [];

        if ($query->mode === 'simple') {
            if ($collectionId = $query->definition['collection_id'] ?? null) {
                $edges[] = ['target_type' => 'collection', 'target_id' => $collectionId, 'kind' => 'lists'];
            }
        } else {
            $views = app(\App\Domain\Collections\Queries\ScopedViewManager::class);
            foreach (ContentCollection::where('site_id', $site->id)->get() as $collection) {
                $referenced = str_contains((string) $query->sql, $views->collectionViewName($collection));
                foreach ($collection->fields() as $field) {
                    if ($field['type'] === 'relation' && str_contains((string) $query->sql, $views->relationViewName($collection, $field['key']))) {
                        $referenced = true;
                    }
                }
                if ($referenced) {
                    $edges[] = ['target_type' => 'collection', 'target_id' => $collection->id, 'kind' => 'lists'];
                }
            }
        }

        $this->references->persistEdges($site->id, 'query', $query->id, $edges);
    }

    private function serialize(SavedQuery $q, ?string $collectionName = null): array
    {
        return [
            'id' => $q->id,
            'name' => $q->name,
            'slug' => $q->slug,
            'mode' => $q->mode,
            'definition' => $q->definition,
            'sql' => $q->sql,
            'public_params' => $q->public_params ?: [],
            'is_public' => $q->is_public,
            'settings' => $q->settings ?: (object) [],
            'collection_name' => $collectionName,
            'created_at' => $q->created_at?->toISOString(),
            'updated_at' => $q->updated_at?->toISOString(),
        ];
    }

    private function serializeResult(array $result): array
    {
        if ($result['type'] === 'records') {
            return [
                'type' => 'records',
                'total' => $result['total'],
                'rows' => collect($result['rows'])->map(fn (Record $r) => [
                    'id' => $r->id, 'title' => $r->title, 'slug' => $r->slug, 'data' => $r->data,
                ])->all(),
            ];
        }

        return $result;
    }
}
