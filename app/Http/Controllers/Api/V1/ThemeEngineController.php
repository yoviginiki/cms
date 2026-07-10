<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeOverride;
use App\Models\ThemeVersion;
use App\Models\Site;
use App\Services\Theme\ThemeResolver;
use App\Services\Theme\ThemeCompiler;
use App\Services\Theme\ValueObjects\ResolveRequest;
use App\Domain\References\Services\StalenessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThemeEngineController extends Controller
{
    public function __construct(
        private ThemeResolver $resolver,
        private ThemeCompiler $compiler,
        private StalenessResolver $staleness,
    ) {}

    /**
     * List all themes available to the tenant (system + tenant-owned).
     */
    public function index(Site $site): JsonResponse
    {
        // System themes (site_id IS NULL — allowed by updated RLS policy)
        $systemThemes = DB::table('themes')
            ->whereNull('site_id')
            ->where('is_system', true)
            ->whereNull('deleted_at')
            ->whereNotNull('document')
            ->get(['id', 'name', 'slug', 'description', 'modes', 'schema_version', 'is_system'])
            ->map(function ($t) {
                $arr = (array) $t;
                $arr['modes'] = json_decode($t->modes ?? '[]', true) ?: [];
                $arr['is_system'] = (bool) $t->is_system;
                return $arr;
            });

        // Site themes (RLS-visible — includes Default and any forked themes)
        $siteThemes = Theme::where('site_id', $site->id)
            ->whereNotNull('document')
            ->get(['id', 'name', 'slug', 'description', 'modes', 'schema_version', 'is_system', 'parent_theme_id']);

        $all = $systemThemes->merge($siteThemes)->unique('id');

        // Mark the single active theme for this site
        $activeThemeId = $site->active_theme_id;

        $all = $all->map(function ($t) use ($activeThemeId) {
            $arr = is_array($t) ? $t : $t->toArray();
            $arr['is_assigned'] = $arr['id'] === $activeThemeId;
            return $arr;
        });

        return response()->json(['data' => $all->values()]);
    }

    /**
     * Get a single theme with its full document.
     */
    public function show(Site $site, string $themeId): JsonResponse
    {
        $theme = $this->findTheme($themeId);
        if (!$theme) {
            return response()->json(['message' => 'Theme not found'], 404);
        }

        return response()->json(['data' => $theme]);
    }

    /**
     * Fork a system theme into a tenant-owned editable copy.
     */
    public function fork(Request $request, Site $site, string $themeId): JsonResponse
    {
        $this->authorize('update', $site);
        $source = $this->findTheme($themeId);
        if (!$source) {
            return response()->json(['message' => 'Source theme not found'], 404);
        }

        $name = $request->input('name', $source->name . ' (Custom)');
        $slug = \Illuminate\Support\Str::slug($name);

        $fork = Theme::create([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $source->description,
            'version' => '1.0.0',
            'config' => $source->config ?? [],
            'manifest_json' => $source->manifest_json ?? [],
            'template_path' => $source->template_path ?? '',
            'document' => $source->document,
            'modes' => $source->modes,
            'schema_version' => $source->schema_version ?? '1.0.0',
            'is_system' => false,
            'parent_theme_id' => $source->id,
            'created_by' => $request->user()?->id,
        ]);

        // Forking creates an editable copy; it does NOT change the live site.
        // Activation is an explicit, separate action (assign) so "fork to
        // experiment" never silently re-themes the published site. Opt in with
        // ?activate=1 to fork-and-activate in one step (flags pages stale).
        $stale = null;
        if ($request->boolean('activate')) {
            $site->update(['active_theme_id' => $fork->id]);
            $stale = $this->staleness->markAllStale($site, "Active theme switched to '{$fork->name}'");
        }

        return response()->json(['data' => $fork, 'meta' => ['stale' => $stale]], 201);
    }

    /**
     * Update a theme's document (only non-system themes).
     */
    public function update(Request $request, Site $site, Theme $theme): JsonResponse
    {
        $this->authorize('update', $site);
        if ($theme->is_system) {
            return response()->json(['message' => 'Cannot edit system themes. Fork first.'], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'document' => ['sometimes', 'array'],
            'modes' => ['sometimes', 'array'],
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $site, $theme) {
            // Snapshot current document before updating
            if ($request->has('document') && $theme->document) {
                ThemeVersion::create([
                    'tenant_id' => $site->tenant_id,
                    'theme_id' => $theme->id,
                    'site_id' => $site->id,
                    'mode' => 'light',
                    'resolved_document' => $theme->document,
                    'content_hash' => hash('sha256', json_encode($theme->document)),
                    'css_artifact_path' => null,
                    'css_artifact_size' => 0,
                ]);
            }

            $theme->update($request->only(['name', 'description', 'document', 'modes']));

            // Invalidate cache
            $this->resolver->invalidateForSite($site->tenant_id, $site->id);

            return response()->json(['data' => $theme->fresh()]);
        });
    }

    /**
     * Resolve the current theme for a site (preview what tokens look like).
     */
    public function resolve(Request $request, Site $site): JsonResponse
    {
        $mode = $request->query('mode', 'light');

        $resolved = $this->resolver->resolveFresh(new ResolveRequest(
            tenantId: $site->tenant_id,
            siteId: $site->id,
            mode: $mode,
        ));

        return response()->json([
            'data' => [
                'tokens' => $resolved->toArray(),
                'css' => $resolved->toCssVariables(),
                'hash' => $resolved->contentHash,
                'count' => count($resolved->tokens),
            ],
        ]);
    }

    /**
     * Assign a theme to a site for a given mode.
     */
    public function assign(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $request->validate([
            'theme_id' => ['nullable', 'string'],
            'page_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $pageId = $request->input('page_id');
        $themeId = $request->input('theme_id');

        // Validate page belongs to this site
        if ($pageId) {
            $pageExists = \App\Models\Page::where('id', $pageId)->where('site_id', $site->id)->exists();
            if (!$pageExists) {
                return response()->json(['message' => 'Page not found'], 404);
            }
        }

        // Clear per-page override
        if ($pageId && !$themeId) {
            ThemeAssignment::where('site_id', $site->id)
                ->where('page_id', $pageId)
                ->delete();

            // page reverts to the site theme → its inlined CSS changes
            \App\Models\Page::where('id', $pageId)->where('status', 'published')
                ->update(['needs_republish' => true, 'needs_republish_reason' => 'Per-page theme override removed']);

            $this->resolver->invalidateForSite($site->tenant_id, $site->id);
            return response()->json(['data' => ['cleared' => true], 'meta' => ['stale' => ['pages' => 1, 'posts' => 0, 'site_wide' => false]]]);
        }

        $theme = $this->findTheme($themeId);
        if (!$theme) {
            return response()->json(['message' => 'Theme not found'], 404);
        }

        if ($pageId) {
            // Per-page theme override
            ThemeAssignment::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'mode' => 'light',
                    'page_id' => $pageId,
                ],
                ['theme_id' => $theme->id],
            );
            // only that page's inlined token CSS changed
            \App\Models\Page::where('id', $pageId)->where('status', 'published')
                ->update(['needs_republish' => true, 'needs_republish_reason' => "Theme '{$theme->name}' assigned to page"]);
            $stale = ['pages' => 1, 'posts' => 0, 'site_wide' => false];
        } else {
            // Site-wide active theme — single source of truth. Token CSS is
            // inlined per page at publish, so every published page/post now
            // carries stale CSS: flag them all for republish.
            $site->update(['active_theme_id' => $theme->id]);
            $stale = $this->staleness->markAllStale($site, "Active theme switched to '{$theme->name}'");
        }

        $this->resolver->invalidateForSite($site->tenant_id, $site->id);

        return response()->json([
            'message' => $pageId ? 'Theme assigned to page' : 'Theme activated',
            'meta' => ['stale' => $stale],
        ]);
    }

    /**
     * Save token overrides for a scope.
     */
    public function saveOverrides(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*.token_path' => ['required', 'string'],
            'overrides.*.value' => ['required'],
            'scope' => ['required', 'in:tenant,site,page,block'],
            'mode' => ['sometimes', 'string'],
            'page_id' => ['sometimes', 'nullable', 'string'],
            'block_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $scope = $request->input('scope');
        $mode = $request->input('mode', 'light');
        // overrides customize the theme currently active on the site — stamp it
        // so they never bleed onto a different theme after a switch
        $themeId = $site->active_theme_id;

        foreach ($request->input('overrides') as $override) {
            ThemeOverride::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'theme_id' => $themeId,
                    'site_id' => $scope === 'site' ? $site->id : null,
                    'page_id' => $scope === 'page' ? $request->input('page_id') : null,
                    'block_id' => $scope === 'block' ? $request->input('block_id') : null,
                    'scope' => $scope,
                    'mode' => $mode,
                    'token_path' => $override['token_path'],
                ],
                ['value' => $override['value']],
            );
        }

        $this->resolver->invalidateForSite($site->tenant_id, $site->id);

        return response()->json(['message' => 'Overrides saved']);
    }

    /**
     * Export a theme as a single self-describing bundle (tokens + metadata +
     * template seeds + preview) — symmetric with import().
     */
    public function export(Site $site, string $themeId): JsonResponse
    {
        $theme = $this->findTheme($themeId);
        if (!$theme) {
            return response()->json(['message' => 'Theme not found'], 404);
        }

        $bundle = app(\App\Services\Theme\ThemePackager::class)->export($theme);

        return response()->json($bundle)
            ->header('Content-Disposition', "attachment; filename=\"{$theme->slug}.theme.json\"");
    }

    /**
     * Import a theme bundle (or a legacy bare W3C document) as a new
     * site-owned theme.
     */
    public function import(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        // Accept either the bundle envelope {stillopress_theme, metadata,
        // document, ...} or a legacy {document: {...}} / bare token document.
        $request->validate([
            'bundle' => ['sometimes', 'array'],
            'document' => ['sometimes', 'array'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);
        if (!$request->has('bundle') && !$request->has('document')) {
            return response()->json(['message' => 'Provide a theme bundle or document'], 422);
        }

        $bundle = $request->input('bundle')
            ?? ['document' => $request->input('document')];

        $theme = app(\App\Services\Theme\ThemePackager::class)
            ->import($site, $bundle, $request->input('name'), $request->user()?->id);

        return response()->json(['data' => $theme], 201);
    }

    /**
     * Get version history for a theme.
     */
    public function versions(Request $request, Site $site): JsonResponse
    {
        $query = ThemeVersion::where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->limit(20);

        // Filter by theme if specified
        if ($themeId = $request->query('theme_id')) {
            $query->where('theme_id', $themeId);
        }

        $versions = $query->get(['id', 'theme_id', 'mode', 'content_hash', 'css_artifact_path', 'css_artifact_size', 'created_at']);

        return response()->json(['data' => $versions]);
    }

    /**
     * Restore a theme to a previous version.
     */
    public function restoreVersion(Request $request, Site $site, string $versionId): JsonResponse
    {
        $this->authorize('update', $site);
        $version = ThemeVersion::where('site_id', $site->id)->findOrFail($versionId);
        $theme = Theme::findOrFail($version->theme_id);

        if ($theme->is_system) {
            return response()->json(['message' => 'Cannot restore system themes'], 403);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($site, $theme, $version) {
        // Snapshot current state before restoring
        if ($theme->document) {
            ThemeVersion::create([
                'tenant_id' => $site->tenant_id,
                'theme_id' => $theme->id,
                'site_id' => $site->id,
                'mode' => 'light',
                'resolved_document' => $theme->document,
                'content_hash' => hash('sha256', json_encode($theme->document)),
                'css_artifact_path' => null,
                'css_artifact_size' => 0,
            ]);
        }

        $theme->update(['document' => $version->resolved_document]);
        $this->resolver->invalidateForSite($site->tenant_id, $site->id);

        return response()->json(['data' => $theme->fresh()]);
        }); // end DB::transaction
    }

    /**
     * Get coverage report for a theme against all blocks.
     */
    public function coverage(Request $request, Site $site, string $themeId): JsonResponse
    {
        $mode = $request->query('mode', 'light');
        $service = app(\App\Services\Theme\Coverage\ThemeCoverageService::class);
        $report = $service->analyze($themeId, $mode);

        return response()->json(['data' => $report->toArray()]);
    }

    /**
     * List available studio frames.
     */
    public function studioFrames(): JsonResponse
    {
        $registry = new \App\Services\Theme\Studio\FrameRegistry();
        return response()->json(['data' => $registry->all()]);
    }

    /**
     * Render a studio frame as HTML (for iframe srcdoc).
     */
    public function studioFrame(Request $request, Site $site, string $slug): \Illuminate\Http\Response
    {
        $themeId = $request->query('theme_id');
        $mode = $request->query('mode', 'light');

        $theme = $themeId ? $this->findTheme($themeId) : null;
        if (!$theme && $site->active_theme_id) {
            $theme = Theme::find($site->active_theme_id);
        }

        // Emit the SAME CSS the published page uses (DesignTokenGenerator),
        // scoped to the theme being previewed — not the semantic-only compiler.
        // This closes the Studio↔published variable-surface gap (was 70 vs 186
        // vars): blocks referencing legacy --color-*/--btn-* aliases now render
        // identically in the Studio iframe and on the live site.
        $css = app(\App\Domain\Theme\Services\DesignTokenGenerator::class)
            ->generateForTheme($theme, $site);

        // A theme's layout personality (cinematic / magazine / business /
        // portfolio / lifestyle …) drives a structurally DIFFERENT sample
        // page in the showcase frame — themes differ in layout, not just color.
        $layout = $theme->document['layout']['style'] ?? 'standard';

        $renderer = new \App\Services\Theme\Studio\FrameRenderer($this->compiler);
        $html = $renderer->render($slug, $css, studio: true, layout: $layout);

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    private function findTheme(string $id): ?Theme
    {
        // RLS policy allows site_id IS NULL (system themes) + tenant's site themes
        return Theme::find($id);
    }
}
