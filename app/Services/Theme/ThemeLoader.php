<?php

namespace App\Services\Theme;

use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeOverride;
use App\Services\Theme\ValueObjects\ResolveRequest;
use Illuminate\Support\Facades\DB;

/**
 * Loads the resolution layer stack from the database.
 */
class ThemeLoader
{
    /**
     * Load all layers for a given resolve request, ordered by precedence.
     *
     * @return array<int, array> Ordered layers (platform defaults, parent theme, active theme, overrides).
     */
    public function loadLayers(ResolveRequest $request): array
    {
        $layers = [];

        // [1] Platform defaults — baked into system theme
        $systemTheme = Theme::where('is_system', true)
            ->whereNull('site_id')
            ->first();

        if ($systemTheme && $systemTheme->document) {
            $layers[] = $systemTheme->document;
        }

        // [2-3] Active theme (with optional parent inheritance)
        $activeTheme = $this->resolveActiveTheme($request);

        if ($activeTheme) {
            // [2] Parent theme document
            if ($activeTheme->parent_theme_id && $activeTheme->parent) {
                $parentDoc = $activeTheme->parent->document;
                if ($parentDoc) $layers[] = $parentDoc;
            }

            // [3] Active theme document
            if ($activeTheme->document) {
                $layers[] = $activeTheme->document;
            }
        }

        // overrides are scoped to the resolved theme so they never bleed
        // across a theme switch (legacy NULL-theme rows still apply)
        $themeId = $activeTheme?->id;

        // [4] Tenant-level overrides (site_id IS NULL)
        $tenantOverrides = $this->loadOverrides($request->tenantId, $themeId, null, null, null, $request->mode);
        if (!empty($tenantOverrides)) {
            $layers[] = $tenantOverrides;
        }

        // [5] Site-level overrides
        if ($request->siteId) {
            $siteOverrides = $this->loadOverrides($request->tenantId, $themeId, $request->siteId, null, null, $request->mode);
            if (!empty($siteOverrides)) {
                $layers[] = $siteOverrides;
            }
        }

        // [6] Page-level overrides
        if ($request->pageId) {
            $pageOverrides = $this->loadOverrides($request->tenantId, $themeId, null, $request->pageId, null, $request->mode);
            if (!empty($pageOverrides)) {
                $layers[] = $pageOverrides;
            }
        }

        // [7] Block-level overrides
        if ($request->blockId) {
            $blockOverrides = $this->loadOverrides($request->tenantId, $themeId, null, null, $request->blockId, $request->mode);
            if (!empty($blockOverrides)) {
                $layers[] = $blockOverrides;
            }
        }

        return $layers;
    }

    /**
     * Find the active theme for the request scope.
     */
    private function resolveActiveTheme(ResolveRequest $request): ?Theme
    {
        // Try site-specific assignment first
        if ($request->siteId) {
            $assignment = ThemeAssignment::where('tenant_id', $request->tenantId)
                ->where('site_id', $request->siteId)
                ->where('mode', $request->mode)
                ->first();

            if ($assignment) {
                return Theme::with('parent')->find($assignment->theme_id);
            }
        }

        // Fall back to tenant default assignment
        $assignment = ThemeAssignment::where('tenant_id', $request->tenantId)
            ->whereNull('site_id')
            ->where('mode', $request->mode)
            ->first();

        if ($assignment) {
            return Theme::with('parent')->find($assignment->theme_id);
        }

        // Fall back to site's active_theme_id (existing system)
        if ($request->siteId) {
            $site = \App\Models\Site::find($request->siteId);
            if ($site && $site->active_theme_id) {
                return Theme::with('parent')->find($site->active_theme_id);
            }
        }

        return null;
    }

    /**
     * Load overrides as a nested W3C-like document fragment.
     */
    private function loadOverrides(string $tenantId, ?string $themeId, ?string $siteId, ?string $pageId, ?string $blockId, string $mode): array
    {
        $query = ThemeOverride::where('tenant_id', $tenantId)
            ->where('mode', $mode)
            // rows for the resolved theme, plus legacy rows written before
            // theme_id existed (theme_id IS NULL) — never another theme's rows
            ->where(function ($q) use ($themeId) {
                $q->whereNull('theme_id');
                if ($themeId) $q->orWhere('theme_id', $themeId);
            });

        if ($siteId) {
            $query->where('site_id', $siteId)->where('scope', 'site');
        } elseif ($pageId) {
            $query->where('page_id', $pageId)->where('scope', 'page');
        } elseif ($blockId) {
            $query->where('block_id', $blockId)->where('scope', 'block');
        } else {
            $query->where('scope', 'tenant');
        }

        $overrides = $query->get();

        if ($overrides->isEmpty()) {
            return [];
        }

        // Convert flat override rows back into a nested document
        $doc = [];
        foreach ($overrides as $override) {
            $this->setNestedValue($doc, $override->token_path, $override->value);
        }

        return $doc;
    }

    /**
     * Set a value at a dot-separated path in a nested array.
     */
    private function setNestedValue(array &$arr, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$arr;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
    }
}
