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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThemeEngineController extends Controller
{
    public function __construct(
        private ThemeResolver $resolver,
        private ThemeCompiler $compiler,
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

        // Mark active — check both new assignments AND legacy active_theme_id
        $assignedIds = ThemeAssignment::where('tenant_id', $site->tenant_id)
            ->pluck('theme_id')
            ->toArray();
        $legacyActiveId = $site->active_theme_id;

        $all = $all->map(function ($t) use ($assignedIds, $legacyActiveId) {
            $arr = is_array($t) ? $t : $t->toArray();
            $arr['is_assigned'] = in_array($arr['id'], $assignedIds) || $arr['id'] === $legacyActiveId;
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

        return response()->json(['data' => $fork], 201);
    }

    /**
     * Update a theme's document (only non-system themes).
     */
    public function update(Request $request, Site $site, Theme $theme): JsonResponse
    {
        if ($theme->is_system) {
            return response()->json(['message' => 'Cannot edit system themes. Fork first.'], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'document' => ['sometimes', 'array'],
            'modes' => ['sometimes', 'array'],
        ]);

        $theme->update($request->only(['name', 'description', 'document', 'modes']));

        // Invalidate cache
        $this->resolver->invalidateForSite($site->tenant_id, $site->id);

        return response()->json(['data' => $theme->fresh()]);
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
        $request->validate([
            'theme_id' => ['required', 'string'],
            'mode' => ['sometimes', 'string'],
        ]);

        $theme = $this->findTheme($request->input('theme_id'));
        if (!$theme) {
            return response()->json(['message' => 'Theme not found'], 404);
        }

        $mode = $request->input('mode', 'light');

        ThemeAssignment::updateOrCreate(
            [
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'mode' => $mode,
            ],
            ['theme_id' => $request->input('theme_id')],
        );

        $this->resolver->invalidateForSite($site->tenant_id, $site->id);

        return response()->json(['message' => 'Theme assigned']);
    }

    /**
     * Save token overrides for a scope.
     */
    public function saveOverrides(Request $request, Site $site): JsonResponse
    {
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

        foreach ($request->input('overrides') as $override) {
            ThemeOverride::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
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
     * Export theme document as W3C tokens JSON.
     */
    public function export(Site $site, string $themeId): JsonResponse
    {
        $theme = $this->findTheme($themeId);
        if (!$theme || !$theme->document) {
            return response()->json(['message' => 'Theme not found'], 404);
        }

        return response()->json($theme->document)
            ->header('Content-Disposition', "attachment; filename=\"{$theme->slug}-tokens.json\"");
    }

    /**
     * Import a W3C tokens JSON document as a new theme.
     */
    public function import(Request $request, Site $site): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'array'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $doc = $request->input('document');
        $name = $request->input('name', $doc['$metadata']['name'] ?? 'Imported Theme');
        $slug = \Illuminate\Support\Str::slug($name);

        $theme = Theme::create([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => $slug,
            'version' => '1.0.0',
            'config' => [],
            'manifest_json' => [],
            'template_path' => '',
            'document' => $doc,
            'modes' => $doc['$metadata']['modes'] ?? ['light'],
            'schema_version' => '1.0.0',
            'is_system' => false,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $theme], 201);
    }

    /**
     * Get version history for a site's theme.
     */
    public function versions(Site $site): JsonResponse
    {
        $versions = ThemeVersion::where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'theme_id', 'mode', 'content_hash', 'css_artifact_path', 'css_artifact_size', 'created_at']);

        return response()->json(['data' => $versions]);
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

        // Resolve the theme tokens
        if ($theme && $theme->document) {
            $merger = new \App\Services\Theme\TokenMerger();
            $refs = new \App\Services\Theme\ReferenceResolver();
            $flat = $refs->flatten($merger->merge([$theme->document]));
            $resolved = new \App\Services\Theme\ValueObjects\ResolvedTheme($flat, hash('sha256', json_encode($flat)));
        } else {
            $resolved = new \App\Services\Theme\ValueObjects\ResolvedTheme([], '');
        }

        $renderer = new \App\Services\Theme\Studio\FrameRenderer($this->compiler);
        $html = $renderer->render($slug, $resolved, studio: true);

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    private function findTheme(string $id): ?Theme
    {
        // RLS policy allows site_id IS NULL (system themes) + tenant's site themes
        return Theme::find($id);
    }
}
