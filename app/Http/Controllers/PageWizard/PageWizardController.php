<?php

namespace App\Http\Controllers\PageWizard;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageWizard\PageWizardSession;
use App\Models\Site;
use App\Services\PageWizard\PageWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Page Wizard API — sibling of ThemeWizardController. Two goals (layout /
 * content) × two flows (one-shot accept, or nudge-then-accept), plus a plain
 * description path. Runtime errors surface as 422 {error}; the wizard produces
 * a real draft page previewed live in an iframe.
 */
class PageWizardController extends Controller
{
    public function __construct(private PageWizardService $wizard)
    {
    }

    public function index(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $sessions = PageWizardSession::where('site_id', $site->id)
            ->whereIn('status', ['drafting', 'capturing'])
            ->latest()
            ->get()
            ->map(fn ($s) => $this->summary($s));

        return response()->json(['data' => $sessions]);
    }

    public function startUrl(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'mode' => ['sometimes', 'in:dom,layout,content'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->startFromUrl(
                $site, $request->user(), $data['url'], $data['mode'] ?? 'dom', $data['hint'] ?? null,
            )),
        ], 201));
    }

    public function startUpload(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->startFromUpload(
                $site, $request->user(), $data['image'], $data['hint'] ?? null,
            )),
        ], 201));
    }

    public function startDescribe(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'description' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->startFromDescription($site, $request->user(), $data['description'])),
        ], 201));
    }

    public function show(Request $request, Site $site, PageWizardSession $pageWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $pageWizardSession);

        return response()->json(['data' => $this->serialize($pageWizardSession)]);
    }

    public function nudge(Request $request, Site $site, PageWizardSession $pageWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $pageWizardSession);
        $data = $request->validate(['instruction' => ['required', 'string', 'min:2', 'max:500']]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->nudge($pageWizardSession, $data['instruction'])),
        ]));
    }

    public function accept(Request $request, Site $site, PageWizardSession $pageWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $pageWizardSession);
        $publish = $request->boolean('publish');

        return $this->guard(function () use ($pageWizardSession, $publish) {
            $page = $this->wizard->accept($pageWizardSession, $publish);

            return response()->json(['data' => [
                'session' => $this->serialize($pageWizardSession->refresh()),
                'page' => ['id' => $page->id, 'slug' => $page->slug, 'title' => $page->title, 'status' => $page->status],
            ]]);
        });
    }

    public function abandon(Request $request, Site $site, PageWizardSession $pageWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $pageWizardSession);
        $this->wizard->abandon($pageWizardSession);

        return response()->json(['data' => ['status' => 'abandoned']]);
    }

    private function authorizeSession(Request $request, Site $site, PageWizardSession $session): void
    {
        abort_unless($session->tenant_id === $request->user()->tenant_id && $session->site_id === $site->id, 404);
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function summary(PageWizardSession $s): array
    {
        return [
            'id' => $s->id,
            'status' => $s->status,
            'mode' => $s->mode,
            'title' => $s->title,
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    private function serialize(PageWizardSession $s): array
    {
        $page = $s->page_id ? Page::find($s->page_id) : null;

        return [
            'id' => $s->id,
            'status' => $s->status,
            'source' => $s->source,
            'mode' => $s->mode,
            'title' => $s->title,
            'reference_url' => $s->reference_url,
            'transcript' => $s->transcript ?? [],
            'manifest' => $s->manifest,
            'page' => $page ? ['id' => $page->id, 'slug' => $page->slug, 'status' => $page->status] : null,
            // Live draft preview: same-origin, auth-cookie'd web route.
            'preview_path' => $page ? "/sites/{$s->site->slug}/{$page->slug}" : null,
            'error' => $s->error,
            'total_tokens' => $s->totalTokens(),
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }
}
