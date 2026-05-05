<?php

namespace App\Domain\Theme\Services;

use App\Models\Site;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemThemeSeeder
{
    /**
     * Install or update all system themes for a site.
     */
    public function seed(Site $site): void
    {
        $systemThemesDir = storage_path('app/themes/system');

        if (!File::isDirectory($systemThemesDir)) {
            return;
        }

        foreach (File::directories($systemThemesDir) as $themeDir) {
            $manifestPath = $themeDir . '/theme.json';
            if (!File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);
            if (!$manifest || empty($manifest['name'])) {
                continue;
            }

            $slug = basename($themeDir);

            $config = json_encode([
                'description' => $manifest['description'] ?? '',
                'tokens' => $manifest['tokens'] ?? [],
                'fonts' => $manifest['fonts'] ?? [],
                'critical_css' => $manifest['critical_css'] ?? '',
                'css_file' => $manifest['css_file'] ?? null,
                'lang' => $manifest['lang'] ?? 'en',
                'screenshot' => $manifest['screenshot'] ?? null,
            ]);

            // Use raw SQL to bypass RLS for system themes (site_id = null)
            $existing = DB::selectOne("SELECT id FROM themes WHERE slug = ? AND is_system = true AND site_id IS NULL", [$slug]);

            if ($existing) {
                DB::statement("UPDATE themes SET name = ?, version = ?, config = ?::jsonb, template_path = ?, updated_at = NOW() WHERE id = ?", [
                    $manifest['name'], $manifest['version'] ?? '1.0.0', $config, "system/{$slug}", $existing->id,
                ]);
                $themeId = $existing->id;
            } else {
                $themeId = (string) \Illuminate\Support\Str::uuid();
                DB::statement("INSERT INTO themes (id, site_id, name, slug, version, config, template_path, is_system, is_active, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?::jsonb, ?, true, false, NOW(), NOW())", [
                    $themeId, $manifest['name'], $slug, $manifest['version'] ?? '1.0.0', $config, "system/{$slug}",
                ]);
            }

            // If site has no active theme, create a site-specific copy and activate it
            if (!$site->active_theme_id) {
                $siteTheme = Theme::create([
                    'site_id' => $site->id,
                    'name' => $manifest['name'],
                    'slug' => $slug,
                    'version' => $manifest['version'] ?? '1.0.0',
                    'config' => json_decode($config, true),
                    'template_path' => "system/{$slug}",
                    'is_system' => false,
                    'is_active' => true,
                    'parent_theme_id' => $themeId,
                ]);

                $site->update(['active_theme_id' => $siteTheme->id]);
            }
        }
    }

    /**
     * List available system themes.
     */
    public function listSystemThemes(): array
    {
        $dir = storage_path('app/themes/system');
        if (!File::isDirectory($dir)) {
            return [];
        }

        $themes = [];
        foreach (File::directories($dir) as $themeDir) {
            $manifestPath = $themeDir . '/theme.json';
            if (!File::exists($manifestPath)) continue;

            $manifest = json_decode(File::get($manifestPath), true);
            if (!$manifest) continue;

            $themes[] = [
                'slug' => basename($themeDir),
                'name' => $manifest['name'] ?? basename($themeDir),
                'version' => $manifest['version'] ?? '1.0.0',
                'description' => $manifest['description'] ?? '',
            ];
        }

        return $themes;
    }
}
