<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Support\RedirectRules;
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

        $isRegex = $request->boolean('is_regex');
        $data = $request->validate([
            'is_regex' => ['sometimes', 'boolean'],
            'source_path' => RedirectRules::sourceRules(required: true, isRegex: $isRegex),
            'target_url' => RedirectRules::targetRules(required: true),
            'status_code' => ['sometimes', 'in:301,302'],
        ]);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            'is_regex' => $isRegex,
            'source_path' => $isRegex ? $data['source_path'] : RedirectRules::normalizeLiteralSource($data['source_path']),
            'target_url' => $data['target_url'],
            'status_code' => $data['status_code'] ?? 301,
        ]);

        return response()->json(['data' => $redirect], 201);
    }

    public function update(Request $request, Site $site, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $site);

        abort_unless($redirect->site_id === $site->id, 404);

        $isRegex = $request->has('is_regex') ? $request->boolean('is_regex') : (bool) $redirect->is_regex;
        $data = $request->validate([
            'is_regex' => ['sometimes', 'boolean'],
            'source_path' => RedirectRules::sourceRules(required: false, isRegex: $isRegex),
            'target_url' => RedirectRules::targetRules(required: false),
            'status_code' => ['sometimes', 'in:301,302'],
        ]);
        if (array_key_exists('source_path', $data) && !$isRegex) {
            $data['source_path'] = RedirectRules::normalizeLiteralSource($data['source_path']);
        }
        $data['is_regex'] = $isRegex;

        $redirect->update($data);

        return response()->json(['data' => $redirect]);
    }

    public function destroy(Site $site, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $site);
        abort_unless($redirect->site_id === $site->id, 404);

        $redirect->delete();

        return response()->json(null, 204);
    }
}
