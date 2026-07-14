<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Services\RecordQueryService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Support\Blocks\RecordDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Track G3 — THE deliberate exception to zero-runtime: a read-only public
 * JSON API for dynamic-tier collections. Published records only, tenant
 * context via SetTenantFromPublicSite (RLS as backstop), responses cached
 * behind a per-collection version key (bumped by RecordService on every
 * write → O(1) invalidation), rate-limited per IP at the route, CORS locked
 * to the site's domains. No write endpoint exists in this namespace — a test
 * asserts the route list.
 *
 * Response rows use the island's index-row shape ({u,t,f,d,i}) so the same
 * search blocks consume either tier.
 */
class PublicCollectionController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(private RecordQueryService $queries)
    {
    }

    public function records(Request $request, Site $site, string $collectionSlug): JsonResponse
    {
        $collection = $this->resolveCollection($site, $collectionSlug);

        $params = $this->params($request, $collection);
        $cacheKey = $this->cacheKey($collection, 'records', $params);

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($site, $collection, $params) {
            $result = $this->queries->search($collection, $params);
            $rows = collect($result['rows']->items())
                ->map(fn (Record $record) => $this->row($site, $collection, $record))
                ->values();

            return [
                'data' => $rows,
                'meta' => [
                    'total' => $result['total'],
                    'per_page' => $result['rows']->perPage(),
                    'next_cursor' => $result['rows']->nextCursor()?->encode(),
                    'facets' => $this->queries->facetCounts($collection, $params),
                ],
            ];
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function record(Request $request, Site $site, string $collectionSlug, string $recordSlug): JsonResponse
    {
        $collection = $this->resolveCollection($site, $collectionSlug);

        $cacheKey = $this->cacheKey($collection, "record:{$recordSlug}", []);

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($site, $collection, $recordSlug) {
            $record = Record::where('collection_id', $collection->id)
                ->where('status', 'published')
                ->where('slug', $recordSlug)
                ->with('relationsOut.toRecord:id,title,slug,status')
                ->first();
            if (!$record) {
                return null;
            }

            $relations = [];
            foreach ($record->relationsOut->sortBy('position') as $edge) {
                if ($edge->toRecord?->status === 'published') {
                    $relations[$edge->relation_key][] = [
                        'title' => $edge->toRecord->title,
                        'slug' => $edge->toRecord->slug,
                        'pivot' => $edge->pivot ?: (object) [],
                    ];
                }
            }

            return [
                'data' => [
                    'title' => $record->title,
                    'slug' => $record->slug,
                    'url' => RecordDisplay::recordUrl($collection, $record),
                    'status' => $record->status,
                    'data' => $this->publicData($site, $collection, $record),
                    'relations' => $relations === [] ? (object) [] : $relations,
                    'published_at' => $record->published_at?->toISOString(),
                ],
            ];
        });

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60');
    }

    private function resolveCollection(Site $site, string $slug): ContentCollection
    {
        $collection = ContentCollection::where('site_id', $site->id)
            ->where('slug', preg_replace('/[^a-z0-9\-]/', '', $slug))
            ->first();

        // The public API serves dynamic-tier collections only — static ones
        // ship their index as flat files; keeping the wall explicit.
        abort_if(!$collection || $collection->tier !== 'dynamic', 404);

        return $collection;
    }

    /** @return array{q: string, facets: array, sort: string, direction: string, per_page: int, cursor: ?string} */
    private function params(Request $request, ContentCollection $collection): array
    {
        $facets = [];
        foreach ($collection->fields() as $field) {
            if (($field['facetable'] ?? false) && ($raw = $request->query($field['key']))) {
                $facets[$field['key']] = array_slice(array_filter(explode(',', (string) $raw)), 0, 10);
            }
        }

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 200),
            'facets' => $facets,
            'sort' => (string) $request->query('sort', ''),
            'direction' => (string) $request->query('direction', 'desc'),
            'per_page' => (int) $request->query('per_page', 20),
            'cursor' => $request->query('cursor') ? (string) $request->query('cursor') : null,
        ];
    }

    /** Island row shape — matches the static index shard contract. */
    private function row(Site $site, ContentCollection $collection, Record $record): array
    {
        $fields = $collection->fields();

        $facets = [];
        $display = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            if ($field['facetable'] ?? false) {
                if ($field['type'] === 'relation') {
                    $titles = $record->relationsOut->where('relation_key', $key)
                        ->map(fn ($e) => $e->toRecord?->title)->filter()->values()->all();
                    if ($titles !== []) {
                        $facets[$key] = $titles;
                    }
                } elseif (($v = $record->data[$key] ?? null) !== null && $v !== '' && $v !== []) {
                    $facets[$key] = $v;
                }
            }
            if (in_array($field['type'], ['text', 'price', 'select', 'sku', 'date', 'boolean', 'number'], true)
                && ($v = $record->data[$key] ?? null) !== null && $v !== '') {
                $display[$key] = $v;
            }
        }

        $row = [
            'u' => RecordDisplay::recordUrl($collection, $record),
            't' => $record->title,
        ];
        if ($facets !== []) {
            $row['f'] = $facets;
        }
        if ($display !== []) {
            $row['d'] = $display;
        }
        if ($thumb = $this->staticThumb($site, $collection, $record)) {
            $row['i'] = $thumb;
        }

        return $row;
    }

    /**
     * Thumbnail as the PUBLISHED static asset path (exists when static detail
     * pages are on — the default). The admin serve URL is auth-gated, useless
     * to a public visitor.
     */
    private function staticThumb(Site $site, ContentCollection $collection, Record $record): ?string
    {
        $key = RecordDisplay::firstImageField($collection);
        $assetId = $key ? ($record->data[$key] ?? null) : null;
        if (!is_string($assetId)) {
            return null;
        }
        $asset = Asset::where('site_id', $site->id)->find($assetId);
        if (!$asset) {
            return null;
        }
        $checksum = $asset->checksum ?: md5($asset->id);
        $extension = pathinfo($asset->filename ?? '', PATHINFO_EXTENSION) ?: 'jpg';

        return "/assets/files/{$checksum}.{$extension}";
    }

    /** Public-safe data map: asset ids swapped for static paths. */
    private function publicData(Site $site, ContentCollection $collection, Record $record): array
    {
        $out = [];
        foreach ($collection->fields() as $field) {
            $value = $record->data[$field['key']] ?? null;
            if ($value === null || $field['type'] === 'relation') {
                continue;
            }
            $out[$field['key']] = $value;
        }

        return $out;
    }

    private function cacheKey(ContentCollection $collection, string $scope, array $params): string
    {
        $version = (int) Cache::get("colapi_ver:{$collection->id}", 0);

        return "colapi:{$collection->id}:{$version}:{$scope}:" . md5(json_encode($params));
    }
}
