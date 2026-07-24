<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Migration\Jobs\RunMigrationToolJob;
use App\Domain\Migration\Support\MigrationRunStore;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin API for the WP-migration toolchain: spider rebuild, redirect maps,
 * and the verification diff (content + optional visual screenshots).
 * Runs execute on the queue; the SPA polls the run record.
 */
class MigrationController extends Controller
{
    public function start(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $data = $request->validate([
            'tool' => 'required|in:spider,redirects,diff',
            'origin' => 'required|url|max:300',
            'options' => 'sometimes|array',
            'options.only' => 'sometimes|array|max:200',
            'options.only.*' => 'string|max:200',
            'options.skip' => 'sometimes|array|max:200',
            'options.skip.*' => 'string|max:200',
            'options.dry' => 'sometimes|boolean',
            'options.deploy' => 'sometimes|boolean',
            'options.new_base' => 'sometimes|nullable|url|max:300',
            'options.limit' => 'sometimes|integer|min:0|max:500',
            'options.include_home' => 'sometimes|boolean',
            'options.screenshots' => 'sometimes|boolean',
        ]);

        // SSRF guard: origin must be a public http(s) host, never internal.
        $host = parse_url($data['origin'], PHP_URL_HOST) ?? '';
        $ip = gethostbyname($host);
        if ($host === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json(['message' => 'Origin must be a public host.'], 422);
        }

        $run = MigrationRunStore::create($site, $data['tool'], rtrim($data['origin'], '/'), $data['options'] ?? []);
        RunMigrationToolJob::dispatch($site->id, $site->tenant_id, $run['id']);

        return response()->json(['data' => $run], 202);
    }

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json(['data' => MigrationRunStore::all($site)]);
    }

    public function show(Site $site, string $runId): JsonResponse
    {
        $this->authorize('view', $site);

        $run = MigrationRunStore::get($site, $runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        return response()->json(['data' => $run]);
    }

    /** Serve a migration artifact (redirect maps, diff report, screenshots). */
    public function artifact(Site $site, string $path): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $site);

        $base = realpath(MigrationRunStore::artifactDir($site));
        if ($base === false) {
            return response()->json(['message' => 'No artifacts yet'], 404);
        }
        $file = realpath($base . '/' . $path);
        if ($file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return response()->json(['message' => 'Artifact not found'], 404);
        }

        $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'json' => 'application/json',
            default => 'text/plain',
        };

        return response()->file($file, ['Content-Type' => $mime]);
    }
}
