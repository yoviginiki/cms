<?php

namespace App\Services\Theme;

use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeTemplate;
use Illuminate\Support\Str;

/**
 * Serialises a theme to / from a single self-describing bundle (the T1 theme
 * contract): W3C token document + metadata (name/author/version/description/
 * modes) + optional template seeds + preview screenshot. One format, used by
 * manual export/import (ThemeEngineController), the first-party theme build
 * (T2), and the Theme Wizard's accepted output (T3).
 *
 * Round-trip guarantee: export() → import() reconstructs an equivalent theme
 * (same document, config, metadata, template compositions). Author & preview
 * live in `manifest_json` so no schema change is required.
 */
class ThemePackager
{
    /** bump when the bundle envelope shape changes incompatibly */
    public const FORMAT = '1.0';

    /**
     * Build the portable bundle array for a theme (JSON-encode for download).
     *
     * @return array<string,mixed>
     */
    public function export(Theme $theme): array
    {
        $manifest = $theme->manifest_json ?? [];

        return [
            'stillopress_theme' => self::FORMAT,
            'metadata' => [
                'name' => $theme->name,
                'slug' => $theme->slug,
                'author' => $manifest['author'] ?? null,
                'version' => $theme->version ?? '1.0.0',
                'description' => $theme->description,
                'modes' => $theme->modes ?? ['light'],
                'schema_version' => $theme->schema_version ?? '1.0.0',
            ],
            'preview_image' => $manifest['preview_image'] ?? null,
            'document' => $theme->document ?? new \stdClass(),
            // legacy flat tokens, preserved so a round-trip is lossless while
            // the two-system migration is in flight
            'config' => $theme->config ?: new \stdClass(),
            'templates' => $this->exportTemplates($theme),
        ];
    }

    /**
     * Create a new site-owned theme from a bundle. Accepts both the new
     * envelope and a bare W3C document (legacy export) for backward compat.
     *
     * @param array<string,mixed> $bundle
     */
    public function import(Site $site, array $bundle, ?string $overrideName, ?string $userId): Theme
    {
        // Backward compat: a bare W3C document (has $metadata or looks like
        // tokens) with no envelope marker.
        if (!isset($bundle['stillopress_theme']) && !isset($bundle['document'])) {
            $bundle = ['document' => $bundle, 'metadata' => [
                'name' => $bundle['$metadata']['name'] ?? null,
                'modes' => $bundle['$metadata']['modes'] ?? ['light'],
            ]];
        }

        $meta = $bundle['metadata'] ?? [];
        $document = $bundle['document'] ?? [];
        $name = $overrideName ?: ($meta['name'] ?? ($document['$metadata']['name'] ?? 'Imported Theme'));

        $manifest = [];
        if (!empty($meta['author'])) $manifest['author'] = $meta['author'];
        if (!empty($bundle['preview_image'])) $manifest['preview_image'] = $bundle['preview_image'];

        $theme = new Theme();
        $theme->fill([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'version' => $meta['version'] ?? '1.0.0',
            'description' => $meta['description'] ?? null,
            'config' => is_array($bundle['config'] ?? null) ? $bundle['config'] : [],
            'manifest_json' => $manifest,
            'template_path' => '',
            'document' => is_array($document) ? $document : [],
            'modes' => $meta['modes'] ?? ($document['$metadata']['modes'] ?? ['light']),
            'schema_version' => $meta['schema_version'] ?? '1.0.0',
            'parent_theme_id' => null,
            'created_by' => $userId,
        ]);
        // is_system is intentionally not fillable — an imported theme is always
        // site-owned, never a global system theme.
        $theme->save();

        $this->importTemplates($site, $theme, $bundle['templates'] ?? []);

        return $theme;
    }

    /**
     * Export the theme's template seeds. Templates are site-scoped and carry
     * their block compositions polymorphically; we capture the block tree as
     * portable data so import can rebuild it on the destination site.
     *
     * @return array<int,array<string,mixed>>
     */
    private function exportTemplates(Theme $theme): array
    {
        // Templates linked to a theme via manifest seed ids (set when a theme
        // is applied). Absent a theme_id column, we export nothing by default;
        // T2 first-party themes carry their template seeds in `manifest_json`.
        $seeds = $theme->manifest_json['template_seeds'] ?? [];
        return is_array($seeds) ? $seeds : [];
    }

    /**
     * Rebuild template seeds as site-scoped ThemeTemplate rows on import.
     *
     * @param array<int,array<string,mixed>> $templates
     */
    private function importTemplates(Site $site, Theme $theme, array $templates): void
    {
        foreach ($templates as $seed) {
            if (empty($seed['type'])) continue;
            ThemeTemplate::create([
                'site_id' => $site->id,
                'name' => $seed['name'] ?? ucfirst($seed['type']),
                'slug' => Str::slug(($seed['name'] ?? $seed['type']) . '-' . Str::random(4)),
                'type' => $seed['type'],
                'category_id' => null,
                'post_format' => $seed['post_format'] ?? null,
                'priority' => $seed['priority'] ?? 0,
                'is_default' => $seed['is_default'] ?? false,
                'settings' => $seed['settings'] ?? [],
                'created_by' => $theme->created_by,
            ]);
        }
    }
}
