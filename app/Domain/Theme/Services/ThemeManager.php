<?php

namespace App\Domain\Theme\Services;

use App\Models\Site;
use App\Models\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ThemeManager
{
    /**
     * Install a theme from a uploaded ZIP file.
     * ZIP must contain a theme.json manifest at root level.
     */
    public function installFromZip(Site $site, UploadedFile $file): Theme
    {
        $tempDir = storage_path('app/themes/temp-' . Str::uuid());
        File::ensureDirectoryExists($tempDir);

        try {
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($file->getRealPath()) !== true) {
                throw new \RuntimeException('Invalid ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            // Find theme.json — could be at root or in a subfolder
            $manifestPath = $this->findManifest($tempDir);
            if (!$manifestPath) {
                throw new \RuntimeException('Missing theme.json manifest in ZIP');
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest || empty($manifest['name'])) {
                throw new \RuntimeException('Invalid theme.json — must have at least a "name" field');
            }

            $themeDir = dirname($manifestPath);

            // Read CSS file if present
            $cssContent = '';
            $cssFile = $manifest['css_file'] ?? 'style.css';
            if (file_exists($themeDir . '/' . $cssFile)) {
                $cssContent = file_get_contents($themeDir . '/' . $cssFile);
            }

            // Read critical CSS if present
            $criticalCss = '';
            if (file_exists($themeDir . '/critical.css')) {
                $criticalCss = file_get_contents($themeDir . '/critical.css');
            } elseif (!empty($manifest['critical_css'])) {
                $criticalCss = $manifest['critical_css'];
            }

            // Store theme assets
            $slug = Str::slug($manifest['name']);
            $assetDir = "themes/{$site->id}/{$slug}";
            $disk = Storage::disk('assets');

            // Copy all theme files to storage
            $files = File::allFiles($themeDir);
            foreach ($files as $f) {
                $relativePath = str_replace($themeDir . '/', '', $f->getPathname());
                if ($relativePath === 'theme.json') continue;
                $disk->put("{$assetDir}/{$relativePath}", file_get_contents($f->getPathname()));
            }

            // Build config from manifest
            $config = [
                'tokens' => $manifest['tokens'] ?? [],
                'critical_css' => $criticalCss ?: ($cssContent ?: null),
                'css_file' => !empty($cssContent) ? null : ($manifest['external_css'] ?? null),
                'fonts' => $manifest['fonts'] ?? [],
                'lang' => $manifest['lang'] ?? 'en',
                'screenshot' => $manifest['screenshot'] ?? null,
                'description' => $manifest['description'] ?? null,
            ];

            // Check for existing theme with same slug
            $existing = Theme::where('site_id', $site->id)->where('slug', $slug)->first();
            if ($existing) {
                $existing->update([
                    'name' => $manifest['name'],
                    'version' => $manifest['version'] ?? '1.0.0',
                    'manifest_json' => $manifest,
                    'config' => $config,
                    'template_path' => $assetDir,
                ]);
                return $existing;
            }

            return Theme::create([
                'site_id' => $site->id,
                'name' => $manifest['name'],
                'slug' => $slug,
                'version' => $manifest['version'] ?? '1.0.0',
                'manifest_json' => $manifest,
                'config' => $config,
                'template_path' => $assetDir,
                'is_system' => false,
            ]);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Activate a theme for a site.
     */
    public function activate(Site $site, Theme $theme): void
    {
        // Deactivate all themes for this site
        Theme::where('site_id', $site->id)->update(['is_active' => false]);

        $theme->update(['is_active' => true]);
        $site->update(['active_theme_id' => $theme->id]);
    }

    /**
     * Export a theme as a ZIP file.
     */
    public function exportAsZip(Theme $theme): string
    {
        $tempZip = storage_path('app/themes/export-' . Str::uuid() . '.zip');
        File::ensureDirectoryExists(dirname($tempZip));

        $zip = new ZipArchive();
        $zip->open($tempZip, ZipArchive::CREATE);

        // Write manifest
        $manifest = $theme->manifest_json ?? [
            'name' => $theme->name,
            'version' => $theme->version ?? '1.0.0',
            'description' => $theme->config['description'] ?? '',
            'tokens' => $theme->config['tokens'] ?? [],
            'fonts' => $theme->config['fonts'] ?? [],
        ];
        $zip->addFromString('theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Write CSS
        if (!empty($theme->config['critical_css'])) {
            $zip->addFromString('style.css', $theme->config['critical_css']);
        }

        // Include stored assets
        if ($theme->template_path) {
            $disk = Storage::disk('assets');
            $files = $disk->allFiles($theme->template_path);
            foreach ($files as $file) {
                $relative = str_replace($theme->template_path . '/', '', $file);
                $zip->addFromString($relative, $disk->get($file));
            }
        }

        $zip->close();

        return $tempZip;
    }

    /**
     * Delete a theme (not system themes).
     */
    public function delete(Theme $theme): void
    {
        if ($theme->is_system) {
            throw new \RuntimeException('Cannot delete system themes');
        }

        // Clean up stored assets
        if ($theme->template_path) {
            Storage::disk('assets')->deleteDirectory($theme->template_path);
        }

        $theme->delete();
    }

    /**
     * List all available themes for a site (system + site-specific).
     */
    public function listForSite(Site $site): array
    {
        $themes = Theme::where(function ($q) use ($site) {
            $q->where('site_id', $site->id)
              ->orWhereNull('site_id');
        })->orderByDesc('is_system')->orderBy('name')->get();

        return $themes->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug ?? Str::slug($t->name),
            'version' => $t->version ?? '1.0.0',
            'description' => $t->config['description'] ?? $t->manifest_json['description'] ?? '',
            'screenshot' => $t->config['screenshot'] ?? null,
            'is_active' => $site->active_theme_id === $t->id,
            'is_system' => $t->is_system,
            'tokens_count' => count($t->config['tokens'] ?? []),
        ])->toArray();
    }

    private function findManifest(string $dir): ?string
    {
        // Check root level
        if (file_exists($dir . '/theme.json')) {
            return $dir . '/theme.json';
        }

        // Check one level deep (in case ZIP has a folder wrapper)
        $subdirs = File::directories($dir);
        foreach ($subdirs as $subdir) {
            if (file_exists($subdir . '/theme.json')) {
                return $subdir . '/theme.json';
            }
        }

        return null;
    }
}
