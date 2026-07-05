<?php

namespace App\Http\Controllers\IssueStudio;

use App\Http\Controllers\Controller;
use App\Models\IssueStudio\StudioSession;
use App\Models\Site;
use App\Services\IssueStudio\IssueStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Route parameter is {studioSession} (not {session}) because the legacy
 * wizard registered a global Route::model('session', WizardSession::class)
 * binding that hijacks the shorter name until Phase 6 removes it.
 */
class IssueStudioController extends Controller
{
    public function __construct(
        private IssueStudioService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = StudioSession::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('updated_at');

        if ($request->query('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        return response()->json(['data' => $query->limit(50)->get()->map(fn ($s) => $this->serialize($s, false))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'required|uuid|exists:sites,id',
            'title' => 'nullable|string|max:200',
        ]);

        $site = Site::findOrFail($data['site_id']);
        abort_unless($site->tenant_id === $request->user()->tenant_id, 403);

        $session = $this->service->create($site, $request->user(), $data['title'] ?? null);

        return response()->json(['data' => $this->serialize($session)], 201);
    }

    public function show(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    public function destroy(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);
        $this->service->abandon($studioSession);

        return response()->json(['success' => true]);
    }

    public function sendMessage(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate(['content' => 'required|string|max:20000']);

        try {
            $studioSession = $this->service->sendMessage($studioSession, $data['content']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function addMaterial(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate([
            'kind' => 'required|in:text,image,interview',
            'title' => 'nullable|string|max:200',
            'content' => 'nullable|string|max:500000',
            'asset_id' => 'nullable|uuid',
        ]);

        try {
            $studioSession = $this->service->addMaterial(
                $studioSession,
                $data['kind'],
                (string) ($data['title'] ?? ''),
                $data['content'] ?? null,
                $data['asset_id'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function removeMaterial(Request $request, StudioSession $studioSession, string $materialId): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        return response()->json(['data' => $this->serialize($this->service->removeMaterial($studioSession, $materialId))]);
    }

    public function completeInterview(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        try {
            $studioSession = $this->service->completeInterview($studioSession);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function generateFlatplan(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        try {
            $studioSession = $this->service->generateFlatplan($studioSession);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function reviseFlatplanSpread(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate([
            'position' => 'required|integer|min:0',
            'instruction' => 'required|string|max:2000',
        ]);

        try {
            $studioSession = $this->service->reviseFlatplanSpread($studioSession, $data['position'], $data['instruction']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function reorderFlatplan(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate([
            'order' => 'required|array|min:2',
            'order.*' => 'integer|min:0',
        ]);

        try {
            $studioSession = $this->service->reorderFlatplan($studioSession, $data['order']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession)]);
    }

    public function approveFlatplan(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        try {
            $studioSession = $this->service->approveFlatplan($studioSession);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    public function generateNextSpread(Request $request, StudioSession $studioSession): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        try {
            $studioSession = $this->service->generateNextSpread($studioSession);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    public function keepSpread(Request $request, StudioSession $studioSession, int $position): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        try {
            $studioSession = $this->service->keepSpread($studioSession, $position);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    public function reviseSpread(Request $request, StudioSession $studioSession, int $position): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate(['instruction' => 'required|string|max:2000']);

        try {
            $studioSession = $this->service->reviseGeneratedSpread($studioSession, $position, $data['instruction']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    public function rethinkSpread(Request $request, StudioSession $studioSession, int $position): JsonResponse
    {
        $this->authorizeSession($request, $studioSession);

        $data = $request->validate(['pattern' => 'nullable|string|max:60']);

        try {
            $studioSession = $this->service->rethinkSpread($studioSession, $position, $data['pattern'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serialize($studioSession, true, true)]);
    }

    private function authorizeSession(Request $request, StudioSession $session): void
    {
        abort_unless($session->tenant_id === $request->user()->tenant_id, 404);
    }

    private function serialize(StudioSession $session, bool $full = true, bool $withSpreads = false): array
    {
        if ($withSpreads) {
            $session->load('spreads');
        }
        $base = [
            'id' => $session->id,
            'site_id' => $session->site_id,
            'title' => $session->title,
            'status' => $session->status,
            'total_tokens' => $session->totalTokens(),
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];

        if (!$full) {
            $brief = $session->brief ?? [];
            $base['topic'] = $brief['topic'] ?? null;
            $base['material_count'] = count($brief['materials'] ?? []);

            return $base;
        }

        return $base + [
            'brief' => $session->brief,
            'transcript' => $session->transcript,
            'flatplan' => $session->flatplan,
            'magazine_issue_id' => $session->magazine_issue_id,
            'token_usage' => $session->token_usage,
            'spreads' => $session->relationLoaded('spreads') ? $session->spreads->toArray() : null,
        ];
    }
}
