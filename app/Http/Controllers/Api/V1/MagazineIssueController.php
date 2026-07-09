<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MagazineIssueController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);
        $issues = MagazineIssue::where('site_id', $site->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'status', 'created_at', 'updated_at']);

        return response()->json(['data' => $issues]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'sometimes|string|in:draft,published,archived',
        ]);

        $issue = MagazineIssue::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'title' => $validated['title'],
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $issue], 201);
    }

    public function update(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorize('update', $site);
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:draft,published,archived',
        ]);

        $issue->update($validated);

        return response()->json(['data' => $issue]);
    }

    public function destroy(Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorize('update', $site);
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $issue->delete();

        return response()->json(null, 204);
    }
}
