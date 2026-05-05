<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $redirects = Redirect::where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $redirects]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'source_path' => ['required', 'string', 'max:2048'],
            'target_url' => ['required', 'string', 'max:2048'],
            'status_code' => ['sometimes', 'in:301,302'],
        ]);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            ...$request->only(['source_path', 'target_url', 'status_code']),
        ]);

        return response()->json(['data' => $redirect], 201);
    }

    public function update(Request $request, Site $site, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'source_path' => ['sometimes', 'string', 'max:2048'],
            'target_url' => ['sometimes', 'string', 'max:2048'],
            'status_code' => ['sometimes', 'in:301,302'],
        ]);

        $redirect->update($request->only(['source_path', 'target_url', 'status_code']));

        return response()->json(['data' => $redirect]);
    }

    public function destroy(Site $site, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $site);

        $redirect->delete();

        return response()->json(null, 204);
    }
}
