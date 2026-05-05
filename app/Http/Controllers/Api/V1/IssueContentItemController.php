<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\IssueContentItem;
use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IssueContentItemController extends Controller
{
    public function store(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        $request->validate([
            'source_type' => ['required', 'string', 'in:post,asset,extra_text,extra_image,extra_video'],
            'source_id' => ['nullable', 'uuid'],
            'extra_payload' => ['nullable', 'array'],
            'importance' => ['sometimes', 'string', 'in:must,should,could'],
            'role_hint' => ['sometimes', 'string', 'in:cover,feature,short,visual_break,closing,none'],
            'editor_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $maxPos = $issue->contentItems()->max('position') ?? -1;

        $item = $issue->contentItems()->create([
            'source_type' => $request->input('source_type'),
            'source_id' => $request->input('source_id'),
            'extra_payload' => $request->input('extra_payload'),
            'importance' => $request->input('importance', 'should'),
            'role_hint' => $request->input('role_hint', 'none'),
            'editor_note' => $request->input('editor_note'),
            'ai_decision' => 'pending',
            'position' => $maxPos + 1,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, Site $site, MagazineIssue $issue, IssueContentItem $item): JsonResponse
    {
        $this->authorizeRole();

        $request->validate([
            'importance' => ['sometimes', 'string', 'in:must,should,could'],
            'role_hint' => ['sometimes', 'string', 'in:cover,feature,short,visual_break,closing,none'],
            'editor_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ai_decision' => ['sometimes', 'string', 'in:kept,dropped,trimmed,pending'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $item->update($request->only(['importance', 'role_hint', 'editor_note', 'ai_decision', 'position']));

        return response()->json(['data' => $item->fresh()]);
    }

    public function destroy(Site $site, MagazineIssue $issue, IssueContentItem $item): JsonResponse
    {
        $this->authorizeRole();
        $item->delete();
        return response()->json(null, 204);
    }

    private function authorizeRole(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'owner'])) {
            abort(403, 'Issue Composer requires admin or owner role.');
        }
    }
}
