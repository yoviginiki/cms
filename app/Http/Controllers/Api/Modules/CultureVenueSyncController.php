<?php

namespace App\Http\Controllers\Api\Modules;

use App\Domain\Collections\Services\RecordService;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\ModuleTenant;
use App\Models\Record;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Culture Engine → Venue objects sync. Upserts venue OBJECTS (theatres,
 * galleries, cultural centres, music venues) from the standalone engine into a
 * per-tenant "Обекти" collection, so the ArtDay site can list venues and — via
 * the events that carry the same venue_slug — show each venue's programme.
 * Mirrors CultureEventSyncController. Idempotent per venue (matched on the
 * external_id field = the engine's source slug).
 *
 * POST /api/modules/culture-engine/venues
 */
class CultureVenueSyncController extends Controller
{
    /** Fields the engine may set (besides the required title). */
    private const FIELDS = [
        'external_id', 'type', 'city', 'address', 'official_url',
        'image_url', 'description', 'event_count',
    ];

    public function __construct(private RecordService $records)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\ModuleToken $token */
        $token = $request->attributes->get('module_token');
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('module_tenant');
        $module = $token->module;

        if (! $tenant) {
            return response()->json(['error' => 'platform_token_not_allowed'], 422);
        }

        $payload = $request->validate([
            'venues' => ['present', 'array', 'max:500'],
            'venues.*.external_id' => ['required', 'string', 'max:64'],
            'venues.*.title' => ['required', 'string', 'max:250'],
            'venues.*.type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'venues.*.city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'venues.*.address' => ['sometimes', 'nullable', 'string', 'max:250'],
            'venues.*.official_url' => ['sometimes', 'nullable', 'url', 'max:600'],
            'venues.*.image_url' => ['sometimes', 'nullable', 'url', 'max:600'],
            'venues.*.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'venues.*.event_count' => ['sometimes', 'integer'],
        ]);

        $site = $this->resolveSite($tenant, $module->id);
        if (! $site) {
            return response()->json(['error' => 'no_target_site'], 422);
        }

        $collection = $this->resolveCollection($tenant, $module->id, $site);
        if (! $collection) {
            return response()->json(['error' => 'no_venues_collection',
                'message' => 'No venues collection (slug "obekti") is configured for this tenant.'], 422);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($payload, $collection, $site, &$created, &$updated) {
            foreach ($payload['venues'] as $venue) {
                $existing = Record::where('collection_id', $collection->id)
                    ->whereField('external_id', $venue['external_id'])
                    ->first();

                $this->records->save($collection, $site, $existing, [
                    'status' => 'published',
                    'data' => $this->toRecordData($venue),
                ]);

                $existing ? $updated++ : $created++;
            }
        });

        return response()->json([
            'collection' => $collection->slug,
            'synced' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $venue
     * @return array<string, mixed>
     */
    private function toRecordData(array $venue): array
    {
        $data = ['title' => $venue['title']];
        foreach (self::FIELDS as $key) {
            if (array_key_exists($key, $venue)) {
                $data[$key] = $venue[$key];
            }
        }

        return $data;
    }

    private function resolveSite(Tenant $tenant, string $moduleId): ?Site
    {
        $pivot = ModuleTenant::where('module_id', $moduleId)->where('tenant_id', $tenant->id)->first();
        $targetId = $pivot?->settings['target_site_id'] ?? null;
        if ($targetId && ($site = Site::where('id', $targetId)->where('tenant_id', $tenant->id)->first())) {
            return $site;
        }

        return Site::where('tenant_id', $tenant->id)->orderBy('created_at')->first();
    }

    private function resolveCollection(Tenant $tenant, string $moduleId, Site $site): ?ContentCollection
    {
        $pivot = ModuleTenant::where('module_id', $moduleId)->where('tenant_id', $tenant->id)->first();
        $collectionId = $pivot?->settings['venues_collection_id'] ?? null;
        if ($collectionId) {
            return ContentCollection::where('id', $collectionId)->where('site_id', $site->id)->first();
        }

        return ContentCollection::where('site_id', $site->id)
            ->where('slug', 'obekti')->first();
    }
}
