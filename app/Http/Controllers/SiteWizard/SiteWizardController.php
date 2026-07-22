<?php

namespace App\Http\Controllers\SiteWizard;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\SiteWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Site Wizard API — tenant-level sibling of PageWizardController: the wizard
 * CREATES the site, so nothing here is site-nested. Start a build from a URL
 * or a design ZIP, poll the session for step/page progress, then accept
 * (publish everything) or abandon (delete the whole site). Runtime errors
 * surface as 422 {error}.
 */
class SiteWizardController extends Controller
{
    public function __construct(private SiteWizardService $wizard)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $sessions = SiteWizardSession::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('status', ['running', 'failed', 'review'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($s) => $this->summary($s));

        return response()->json(['data' => $sessions]);
    }

    public function startUrl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'max_pages' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'site_id' => ['sometimes', 'nullable', 'uuid'],
            'menu_label' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        $this->authorizeTarget($request, $data['site_id'] ?? null);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->startFromUrl($request->user(), $data['url'], [
                'name' => $data['name'] ?? null,
                'max_pages' => $data['max_pages'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'menu_label' => $data['menu_label'] ?? null,
            ])),
        ], 201));
    }

    public function startZip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:' . ((int) config('cms.site_wizard.zip_max_mb', 100) * 1024)],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'site_id' => ['sometimes', 'nullable', 'uuid'],
            'menu_label' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        $this->authorizeTarget($request, $data['site_id'] ?? null);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->startFromZip($request->user(), $data['file'], [
                'name' => $data['name'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'menu_label' => $data['menu_label'] ?? null,
            ])),
        ], 201));
    }

    /** New site → may the user create sites; into a target → may they edit THAT site. */
    private function authorizeTarget(Request $request, ?string $siteId): void
    {
        if ($siteId) {
            $site = Site::where('tenant_id', $request->user()->tenant_id)->findOrFail($siteId);
            $this->authorize('update', $site);
        } else {
            $this->authorize('create', Site::class);
        }
    }

    public function show(Request $request, SiteWizardSession $siteWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $siteWizardSession);

        return response()->json(['data' => $this->serialize($siteWizardSession)]);
    }

    public function accept(Request $request, SiteWizardSession $siteWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $siteWizardSession);

        return $this->guard(function () use ($siteWizardSession) {
            $site = $this->wizard->accept($siteWizardSession);

            return response()->json(['data' => [
                'session' => $this->serialize($siteWizardSession->refresh()),
                'site' => ['id' => $site->id, 'slug' => $site->slug, 'name' => $site->name],
            ]]);
        });
    }

    public function abandon(Request $request, SiteWizardSession $siteWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $siteWizardSession);

        return $this->guard(function () use ($siteWizardSession) {
            $this->wizard->abandon($siteWizardSession);

            return response()->json(['data' => ['status' => 'abandoned']]);
        });
    }

    public function retry(Request $request, SiteWizardSession $siteWizardSession): JsonResponse
    {
        $this->authorizeSession($request, $siteWizardSession);

        return $this->guard(fn () => response()->json([
            'data' => $this->serialize($this->wizard->retry($siteWizardSession)),
        ]));
    }

    private function authorizeSession(Request $request, SiteWizardSession $session): void
    {
        abort_unless($session->tenant_id === $request->user()->tenant_id, 404);
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function summary(SiteWizardSession $s): array
    {
        return [
            'id' => $s->id,
            'status' => $s->status,
            'source' => $s->source,
            'title' => $s->title,
            'reference_url' => $s->reference_url,
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    private function serialize(SiteWizardSession $s): array
    {
        $site = $s->site_id ? Site::find($s->site_id) : null;
        $pages = ($s->page_ids ?? []) !== []
            ? Page::whereIn('id', $s->page_ids)->get(['id', 'title', 'slug', 'status'])
            : collect();

        return [
            'id' => $s->id,
            'status' => $s->status,
            'source' => $s->source,
            'mode' => $s->mode(),
            'title' => $s->title,
            'reference_url' => $s->reference_url,
            'steps' => $s->steps ?? [],
            // Per-page progress for the checklist — cached manifests stay server-side.
            'sources' => array_map(fn ($src) => collect($src)->except('manifest')->all(), $s->sources ?? []),
            'site' => $site ? ['id' => $site->id, 'slug' => $site->slug, 'name' => $site->name] : null,
            'pages' => $pages->map(fn ($p) => [
                'id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'status' => $p->status,
            ])->values(),
            'theme' => $this->themeSwatches($s),
            'menu' => $this->menuPreview($s),
            'error' => $s->error,
            'total_tokens' => $s->totalTokens(),
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Menu preview for the review screen. 'new' mode: the created menu's
     * items. 'into' mode: the parent item's label + its submenu children.
     */
    private function menuPreview(SiteWizardSession $s): ?array
    {
        if ($s->mode() === 'into') {
            if (!$s->menu_item_id) {
                return null;
            }
            $parent = \App\Models\MenuItem::find($s->menu_item_id);
            if (!$parent) {
                return null;
            }
            $children = \App\Models\MenuItem::where('parent_id', $parent->id)
                ->orderBy('sort_order')
                ->get(['label', 'page_id'])
                ->map(fn ($i) => ['label' => $i->label, 'is_page' => $i->page_id !== null])
                ->values()
                ->all();

            return array_merge([['label' => $parent->label . ' ▾', 'is_page' => $parent->page_id !== null]], $children);
        }

        if (!$s->menu_id) {
            return null;
        }

        return $s->menu?->items()->orderBy('sort_order')->get(['label', 'page_id', 'url'])
            ->map(fn ($i) => ['label' => $i->label, 'is_page' => $i->page_id !== null])->values()->all();
    }

    /** Swatches + type for the review screen, straight from the token profile. */
    private function themeSwatches(SiteWizardSession $s): ?array
    {
        $profile = $s->profile;
        if (!is_array($profile) || empty($profile['palette'])) {
            return null;
        }

        return [
            'name' => $profile['name'] ?? null,
            'palette' => $profile['palette'],
            'typography' => [
                'display' => $profile['typography']['display_character'] ?? null,
                'body' => $profile['typography']['body_character'] ?? null,
            ],
            'design_read' => $profile['design_read'] ?? null,
        ];
    }
}
