<?php

namespace App\Http\Controllers\ThemeWizard;

use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeWizard\WizardSession;
use App\Services\Theme\Studio\FrameRenderer;
use App\Services\Theme\ThemeCompiler;
use App\Services\ThemeWizard\ThemeWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Theme Wizard (T3 W3): conversational theme creation. Start from a reference
 * (URL or upload), refine with nudges, preview the live candidate, accept into
 * a real site theme. AI endpoints are throttled; RuntimeExceptions → 422 with
 * {error} (the frontend store reads response.data.error).
 */
class ThemeWizardController extends Controller
{
    public function __construct(private ThemeWizardService $wizard) {}

    public function index(Site $site): JsonResponse
    {
        $sessions = WizardSession::where('site_id', $site->id)
            ->where('status', 'drafting')
            ->latest()
            ->get()
            ->map(fn ($s) => $this->summary($s));

        return response()->json(['data' => $sessions]);
    }

    public function startUrl(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize(
                $this->wizard->startFromUrl($site, $request->user(), $data['url'], $data['hint'] ?? null)
            ),
        ], 201));
    }

    public function startUpload(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize(
                $this->wizard->startFromUpload($site, $request->user(), $request->file('image'), $request->input('hint'))
            ),
        ], 201));
    }

    public function startConversation(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:800'],
        ]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize(
                $this->wizard->startFromConversation($site, $request->user(), $data['description'])
            ),
        ], 201));
    }

    public function show(Request $request, Site $site, WizardSession $wizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $wizardSession);
        return response()->json(['data' => $this->serialize($wizardSession)]);
    }

    public function nudge(Request $request, Site $site, WizardSession $wizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $wizardSession);
        $data = $request->validate(['instruction' => ['required', 'string', 'max:400']]);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->nudge($wizardSession, $data['instruction'])),
        ]));
    }

    public function accept(Request $request, Site $site, WizardSession $wizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $wizardSession);
        return $this->guard(function () use ($wizardSession) {
            $theme = $this->wizard->accept($wizardSession);
            return response()->json(['data' => ['theme_id' => $theme->id, 'name' => $theme->name]]);
        });
    }

    public function abandon(Request $request, Site $site, WizardSession $wizardSession): JsonResponse
    {
        $this->authorizeSession($request, $site, $wizardSession);
        $this->wizard->abandon($wizardSession);
        return response()->json(['data' => ['abandoned' => true]]);
    }

    /**
     * Live preview of the session's current candidate — same showcase frame the
     * theme picker uses, rendered from the candidate document (not yet a theme).
     */
    public function preview(Request $request, Site $site, WizardSession $wizardSession, string $slug = 'showcase'): \Illuminate\Http\Response
    {
        $this->authorizeSession($request, $site, $wizardSession);
        $doc = $wizardSession->candidate['document'] ?? null;
        if (!$doc) {
            return response('<p>No draft yet.</p>', 200)->header('Content-Type', 'text/html');
        }

        $theme = new Theme(['document' => $doc]);
        $css = app(DesignTokenGenerator::class)->generateForTheme($theme, $site);
        $layout = $doc['layout']['style'] ?? 'standard';

        $html = (new FrameRenderer(app(ThemeCompiler::class)))->render('showcase', $css, studio: true, layout: $layout);
        return response($html, 200)->header('Content-Type', 'text/html');
    }

    // ── helpers ──

    private function authorizeSession(Request $request, Site $site, WizardSession $session): void
    {
        abort_unless($session->tenant_id === $request->user()->tenant_id && $session->site_id === $site->id, 404);
    }

    /** Full session payload for the wizard UI. */
    private function serialize(WizardSession $s): array
    {
        $cand = $s->candidate ?? [];
        return [
            'id' => $s->id,
            'status' => $s->status,
            'source' => $s->source,
            'title' => $s->title,
            'reference_url' => $s->reference_url,
            'transcript' => $s->transcript ?? [],
            'profile' => $s->profile,
            'candidate' => [
                'name' => $cand['name'] ?? null,
                'description' => $cand['description'] ?? null,
                'layout' => $cand['document']['layout']['style'] ?? null,
                'fonts' => $cand['document']['wizard'] ?? null,
            ],
            'theme_id' => $s->theme_id,
            'total_tokens' => $s->totalTokens(),
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    /** Lightweight list entry. */
    private function summary(WizardSession $s): array
    {
        return [
            'id' => $s->id, 'title' => $s->title, 'status' => $s->status,
            'source' => $s->source, 'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    /** Turn RuntimeExceptions (AI/validation/budget) into 422 {error}. */
    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
