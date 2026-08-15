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
 * Culture Engine → Collection sync (Track G, spec §44/§95.6). Upserts approved
 * events from the standalone engine into a per-tenant "Cultural events"
 * collection so the ArtDay site can render a calendar / browsable collection /
 * search from real records. Idempotent per event (matched on the external_id
 * field = the engine's event uuid). Draft-only publishing rules do not apply
 * here — these are structured data rows, published so the static site can show
 * them; a human still controls whether the site itself is (re)published.
 *
 * POST /api/modules/culture-engine/events
 * Auth + tenant context + module-enabled: route middleware
 * (module.token:events:sync → module:culture-engine).
 */
class CultureEventSyncController extends Controller
{
    /** Fields the engine may set; anything else is ignored by the schema processor. */
    private const FIELDS = [
        'external_id', 'start_date', 'time', 'category', 'city', 'venue',
        'price', 'is_free', 'ticket_url', 'official_url', 'image_url',
        'description', 'source', 'venue_slug',
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
            'events' => ['present', 'array', 'max:500'],
            'events.*.external_id' => ['required', 'string', 'max:64'],
            'events.*.title' => ['required', 'string', 'max:250'],
            'events.*.start_date' => ['sometimes', 'nullable', 'date'],
            'events.*.city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.venue' => ['sometimes', 'nullable', 'string', 'max:200'],
            'events.*.category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'events.*.is_free' => ['sometimes', 'boolean'],
            'events.*.time' => ['sometimes', 'nullable', 'string', 'max:40'],
            'events.*.price' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'events.*.source' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.venue_slug' => ['sometimes', 'nullable', 'string', 'max:64'],
            'events.*.ticket_url' => ['sometimes', 'nullable', 'url', 'max:600'],
            'events.*.official_url' => ['sometimes', 'nullable', 'url', 'max:600'],
            'events.*.image_url' => ['sometimes', 'nullable', 'url', 'max:600'],
        ]);

        $site = $this->resolveSite($tenant, $module->id);
        if (! $site) {
            return response()->json(['error' => 'no_target_site'], 422);
        }

        $collection = $this->resolveCollection($tenant, $module->id, $site);
        if (! $collection) {
            return response()->json(['error' => 'no_events_collection',
                'message' => 'No events collection is configured for this tenant (settings.events_collection_id).'], 422);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($payload, $collection, $site, &$created, &$updated) {
            foreach ($payload['events'] as $event) {
                $existing = Record::where('collection_id', $collection->id)
                    ->whereField('external_id', $event['external_id'])
                    ->first();

                $this->records->save($collection, $site, $existing, [
                    'status' => 'published',
                    'data' => $this->toRecordData($event),
                ]);

                $existing ? $updated++ : $created++;
            }
        });

        // Re-bake the ArtDay calendar page's inline event data so it stays in
        // sync (the widget reads baked data, to work on the editor preview too).
        // Queued — the CMS workers publish it; no-op if the site has no calendar.
        if ($created + $updated > 0) {
            // Delay + ShouldBeUnique: chunked syncs collapse to one refresh that
            // runs once the whole batch has landed.
            \App\Domain\Culture\RefreshEventCalendarJob::dispatch($site->id, $collection->id, $tenant->id)
                ->delay(now()->addSeconds(20));
        }

        return response()->json([
            'collection' => $collection->slug,
            'synced' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function toRecordData(array $event): array
    {
        $data = ['title' => $event['title']];
        foreach (self::FIELDS as $key) {
            if (array_key_exists($key, $event)) {
                $data[$key] = $event[$key];
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
        $collectionId = $pivot?->settings['events_collection_id'] ?? null;
        if ($collectionId) {
            return ContentCollection::where('id', $collectionId)->where('site_id', $site->id)->first();
        }

        return ContentCollection::where('site_id', $site->id)
            ->where('slug', 'kulturni-sabitiya')->first();
    }
}
