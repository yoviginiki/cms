<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Services\CollectionService;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\EntityReference;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for Collections (Track G): the schema-bearing containers. Records have
 * their own controller. Delete protection follows the house 409+force pattern.
 */
class CollectionController extends Controller
{
    public function __construct(
        private CollectionService $service,
        private ReferenceUsageService $usage,
        private StalenessResolver $staleness,
    ) {
    }

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $collections = ContentCollection::where('site_id', $site->id)
            ->withCount('records')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $collections->map(fn ($c) => $this->serialize($c))]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $collection = $this->service->create($site, $request->all());
        $collection->loadCount('records');

        return response()->json(['data' => $this->serialize($collection)], 201);
    }

    public function show(Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('view', $site);
        $this->assertOnSite($site, $collection);

        $collection->loadCount('records');

        return response()->json(['data' => $this->serialize($collection)]);
    }

    public function update(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $result = $this->service->update($collection, $site, $request->all());
        $result['collection']->loadCount('records');

        return response()->json([
            'data' => $this->serialize($result['collection']),
            'warnings' => $result['warnings'],
        ]);
    }

    public function destroy(Request $request, Site $site, ContentCollection $collection): JsonResponse
    {
        $this->authorize('update', $site);
        $this->assertOnSite($site, $collection);

        $recordCount = Record::where('collection_id', $collection->id)->count();
        $dependents = $this->service->relationDependents($collection);
        $usage = $this->usage->usage($site, 'collection', $collection->id);

        if (($recordCount > 0 || $dependents !== [] || $usage['count'] > 0) && !$request->boolean('force')) {
            return response()->json([
                'message' => "Collection '{$collection->name}' still has data or is in use. Pass force=1 to delete anyway.",
                'recordCount' => $recordCount,
                'relationDependents' => $dependents,
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        DB::transaction(function () use ($site, $collection) {
            // Drop the reference edges owned by this collection's records so
            // the graph doesn't accumulate dangling sources; edges *to* them
            // dangle like every other force-delete in the system.
            EntityReference::where('site_id', $site->id)
                ->where('source_type', 'record')
                ->whereIn('source_id', Record::where('collection_id', $collection->id)->select('id'))
                ->delete();

            $this->staleness->markStale($site, 'collection', $collection->id, 'collection_deleted');
            $collection->delete(); // records + record_relations cascade
        });

        return response()->json(['message' => 'Collection deleted.']);
    }

    private function serialize(ContentCollection $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'tier' => $c->tier,
            'schema' => $c->schema,
            'settings' => $c->settings ?: (object) [],
            'is_system' => $c->is_system,
            'records_count' => $c->records_count ?? null,
            'created_at' => $c->created_at?->toISOString(),
            'updated_at' => $c->updated_at?->toISOString(),
        ];
    }

    private function assertOnSite(Site $site, ContentCollection $collection): void
    {
        abort_if($collection->site_id !== $site->id, 404);
    }
}
