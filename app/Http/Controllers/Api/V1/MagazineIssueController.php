<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagazineIssueController extends Controller
{
    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorizeRole();

        $query = MagazineIssue::forSite($site->id)->orderByDesc('updated_at');

        if ($status = $request->query('status')) {
            $query->byStatus($status);
        }

        return response()->json(['data' => $query->paginate($request->integer('per_page', 20))]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorizeRole();

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'theme' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'intention' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tone_knobs' => ['sometimes', 'nullable', 'array'],
            'target_page_count' => ['sometimes', 'integer', 'min:4', 'max:100'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $issue = MagazineIssue::create([
            'tenant_id' => Auth::user()->tenant_id,
            'site_id' => $site->id,
            'title' => $request->input('title'),
            'subtitle' => $request->input('subtitle'),
            'theme' => $request->input('theme'),
            'intention' => $request->input('intention'),
            'tone_knobs' => $request->input('tone_knobs', []),
            'target_page_count' => $request->input('target_page_count', 20),
            'language' => $request->input('language', 'en'),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return response()->json(['data' => $issue->load(['contentItems', 'designSystem'])], 201);
    }

    public function show(Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        $issue->load([
            'contentItems' => fn($q) => $q->orderBy('position'),
            'designSystem',
        ]);

        // Attach source post titles to content items
        $postIds = $issue->contentItems->where('source_type', 'post')->pluck('source_id')->filter()->unique()->toArray();
        if (!empty($postIds)) {
            $posts = \App\Models\Post::whereIn('id', $postIds)->get(['id', 'title', 'slug', 'excerpt', 'featured_image'])->keyBy('id');
            foreach ($issue->contentItems as $item) {
                if ($item->source_type === 'post' && $item->source_id && isset($posts[$item->source_id])) {
                    $post = $posts[$item->source_id];
                    $item->setAttribute('post_title', $post->title);
                    $item->setAttribute('post_slug', $post->slug);
                    $item->setAttribute('post_excerpt', $post->excerpt);
                    $item->setAttribute('post_image', $post->featured_image);
                }
            }
        }

        // Get latest curation run per phase (bypass relationship's default order)
        $latestRuns = \App\Domain\IssueComposer\Models\MagazineCurationRun::where('issue_id', $issue->id)
            ->selectRaw('DISTINCT ON (phase) *')
            ->orderByRaw('phase ASC, created_at DESC')
            ->get()
            ->keyBy('phase');

        $data = $issue->toArray();
        $data['latest_runs'] = $latestRuns;

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'theme' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'intention' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tone_knobs' => ['sometimes', 'nullable', 'array'],
            'target_page_count' => ['sometimes', 'integer', 'min:4', 'max:100'],
            'language' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:draft,intake,curating,laid_out,ready,handed_off'],
        ]);

        $issue->update($request->only([
            'title', 'subtitle', 'theme', 'intention', 'tone_knobs',
            'target_page_count', 'language', 'status',
        ]));

        return response()->json(['data' => $issue->fresh()]);
    }

    public function destroy(Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();
        $issue->delete();
        return response()->json(null, 204);
    }

    /**
     * Trigger AI curation run.
     */
    public function runCuration(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        $request->validate([
            'directive' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locked_section_ids' => ['sometimes', 'array'],
        ]);

        try {
            $service = app(\App\Services\Ai\CurationRunService::class);
            $run = $service->run(
                $issue,
                $request->input('directive'),
                $request->input('locked_section_ids', [])
            );

            return response()->json(['data' => $run]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Curation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get latest curation run output.
     */
    public function latestCuration(Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        $run = $issue->curationRuns()
            ->where('phase', 'curation')
            ->orderByDesc('created_at')
            ->first();

        if (!$run) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $run]);
    }

    public function runLayout(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        try {
            $service = app(\App\Services\Ai\LayoutRunService::class);
            $run = $service->run($issue);

            return response()->json(['data' => $run]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Layout failed: ' . $e->getMessage()], 500);
        }
    }

    public function handoff(Request $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorizeRole();

        try {
            $renderer = app(\App\Services\Magazine\DirectRenderer::class);
            $page = $renderer->materialize($issue);

            return response()->json(['data' => [
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'status' => 'handed_off',
            ]]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Handoff failed: ' . $e->getMessage()], 500);
        }
    }

    private function authorizeRole(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'owner'])) {
            abort(403, 'Issue Composer requires admin or owner role.');
        }
    }
}
