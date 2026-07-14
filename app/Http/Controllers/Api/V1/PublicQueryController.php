<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Queries\QueryRunner;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Models\SavedQuery;
use App\Models\Site;
use App\Support\Blocks\RecordDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Track G-Q — a saved query as a read-only public endpoint:
 * GET api/v1/public/{site}/queries/{query-slug}. Only queries flagged
 * is_public; the ONLY accepted request parameters are the query's declared
 * typed public_params (undeclared → 422). Same cache/rate-limit/CORS regime
 * as the records API — cache version keyed to the source collection, so a
 * record write invalidates instantly.
 */
class PublicQueryController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(private QueryRunner $runner)
    {
    }

    public function show(Request $request, Site $site, string $querySlug): JsonResponse
    {
        $query = SavedQuery::where('site_id', $site->id)
            ->where('slug', preg_replace('/[^a-z0-9\-]/', '', $querySlug))
            ->where('is_public', true)
            ->first();
        abort_if(!$query, 404);

        $params = $request->query();
        $collectionId = $query->definition['collection_id'] ?? 'none';
        $version = (int) Cache::get("colapi_ver:{$collectionId}", 0);
        $cacheKey = "colapi:q:{$query->id}:{$version}:" . md5(json_encode($params) . $query->updated_at?->timestamp);

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($site, $query, $params) {
            $result = $this->runner->run($query, $params);

            if ($result['type'] === 'records') {
                $collection = $query->sourceCollection();

                return [
                    'data' => [
                        'type' => 'records',
                        'rows' => collect($result['rows'])->map(fn (Record $r) => array_filter([
                            'u' => $collection ? RecordDisplay::recordUrl($collection, $r) : null,
                            't' => $r->title,
                            'd' => $r->data,
                        ]))->all(),
                        'total' => $result['total'],
                    ],
                ];
            }

            return ['data' => $result];
        });

        return response()->json($payload)->header('Cache-Control', 'public, max-age=60');
    }
}
