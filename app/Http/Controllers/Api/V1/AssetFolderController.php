<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Folders of the site's asset library. A folder is a path string; it exists
 * if it has a row in asset_folders OR any asset points at it (or below it).
 */
class AssetFolderController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $counts = Asset::query()
            ->where('site_id', $site->id)
            ->whereNotNull('folder')
            ->select('folder', DB::raw('count(*) as n'))
            ->groupBy('folder')
            ->pluck('n', 'folder');

        $paths = collect(AssetFolder::where('site_id', $site->id)->pluck('path'))
            ->merge($counts->keys());

        // Every ancestor of a used path is a folder too ("a/b/c" implies "a" and "a/b").
        $all = collect();
        foreach ($paths as $path) {
            $acc = '';
            foreach (explode('/', $path) as $seg) {
                $acc = $acc === '' ? $seg : "{$acc}/{$seg}";
                $all->push($acc);
            }
        }

        $folders = $all->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->map(fn (string $path) => [
            'path' => $path,
            'name' => AssetFolder::nameOf($path),
            'parent' => AssetFolder::parentOf($path),
            'count' => (int) ($counts[$path] ?? 0),
        ]);

        $root = Asset::where('site_id', $site->id)->whereNull('folder')->count();

        return response()->json(['data' => $folders, 'root_count' => $root]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('upload', [Asset::class, $site]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $parent = AssetFolder::normalizePath($validated['parent'] ?? null);
            $path = AssetFolder::normalizePath(($parent ? $parent . '/' : '') . $validated['name']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['name' => [$e->getMessage()]]], 422);
        }
        if ($path === null) {
            return response()->json(['message' => 'Folder name is required.', 'errors' => ['name' => ['Folder name is required.']]], 422);
        }

        $folder = AssetFolder::firstOrCreate(['site_id' => $site->id, 'path' => $path]);

        return response()->json(['data' => [
            'path' => $folder->path,
            'name' => AssetFolder::nameOf($folder->path),
            'parent' => AssetFolder::parentOf($folder->path),
            'count' => 0,
        ]], $folder->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Delete a folder (and its sub-folders). Assets inside are moved to the
     * parent folder — files are never deleted from here.
     */
    public function destroy(Request $request, Site $site): JsonResponse
    {
        $this->authorize('upload', [Asset::class, $site]);

        try {
            $path = AssetFolder::normalizePath($request->input('path'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if ($path === null) {
            return response()->json(['message' => 'Cannot delete the root folder.'], 422);
        }

        $parent = AssetFolder::parentOf($path);

        DB::transaction(function () use ($site, $path, $parent) {
            Asset::where('site_id', $site->id)
                ->where(fn ($q) => $q->where('folder', $path)->orWhere('folder', 'like', $path . '/%'))
                ->update(['folder' => $parent]);

            AssetFolder::where('site_id', $site->id)
                ->where(fn ($q) => $q->where('path', $path)->orWhere('path', 'like', $path . '/%'))
                ->delete();
        });

        return response()->json(null, 204);
    }
}
