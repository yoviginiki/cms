<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\LocalePaths;
use App\Domain\Sites\Services\StarterTemplateService;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Theme demo gallery: builds one demo site per SYSTEM theme (starter "full"
 * template content) and publishes them under {target}/themes/{slug}/ with a
 * gallery index — both a public showcase and the platform's cross-theme
 * quality fleet (identical content rendered by every theme isolates CMS
 * output bugs from theme bugs under Lighthouse/schema testing).
 *
 * Demo sites persist in the CMS (slug theme-demo-*) so demos are editable
 * and re-publishable; the /themes path survives full publishes via
 * publishing.preserve_paths (DeployService::pruneStale).
 */
class PublishThemeDemosCommand extends Command
{
    protected $signature = 'themes:publish-demos
        {--target= : Docroot to publish into (default: the stillopress.com docroot)}
        {--theme= : Only this theme slug}';

    protected $description = 'Build and publish the /themes demo gallery for all system themes';

    public function handle(StarterTemplateService $starter, BuildPageService $builder, ArchiveBuildService $archives): int
    {
        $tenant = DB::selectOne('SELECT id FROM tenants LIMIT 1');
        DB::unprepared("SET app.current_tenant_id = '" . preg_replace('/[^a-f0-9\-]/', '', $tenant->id) . "'");

        $target = rtrim($this->option('target') ?: '/home/cytechno/web/stillopress.com/public_html', '/');
        if (!is_dir($target)) {
            $this->error("Target docroot missing: {$target}");

            return self::FAILURE;
        }

        $themes = Theme::where('is_system', true)
            ->when($this->option('theme'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('name')->get();

        $published = [];
        foreach ($themes as $theme) {
            $slug = 'theme-demo-' . $theme->slug;
            $site = Site::firstOrCreate(
                ['slug' => $slug],
                ['tenant_id' => $tenant->id, 'name' => "{$theme->name} Demo", 'status' => 'active', 'settings' => []]
            );
            if ($site->active_theme_id !== $theme->id) {
                $site->update(['active_theme_id' => $theme->id]);
            }
            if ($site->pages()->count() === 0) {
                $starter->apply($site, 'full'); // generic starter content, no AI call
            }
            $site = $site->fresh();
            $site->load('theme');

            $staging = storage_path('app/theme-demos/' . $theme->slug);
            File::deleteDirectory($staging);
            File::ensureDirectoryExists($staging);
            AssetPublisher::reset();
            AssetPublisher::setDeployTarget($staging);

            // Pages + posts through the real publish renderer
            foreach ($site->pages()->where('status', 'published')->get() as $page) {
                $html = $builder->build($page, $site->theme, $site);
                $path = LocalePaths::pagePath($site, $page);
                File::ensureDirectoryExists(dirname("{$staging}/{$path}"));
                File::put("{$staging}/{$path}", $html);
            }
            foreach ($site->posts()->with('category')->where('status', 'published')->get() as $post) {
                $html = $builder->build($post, $site->theme, $site);
                $path = LocalePaths::postPath($site, $post);
                File::ensureDirectoryExists(dirname("{$staging}/{$path}"));
                File::put("{$staging}/{$path}", $html);
            }
            $archives->buildAll($site, $staging);
            AssetPublisher::reset();

            // Root-absolute URLs → subdirectory URLs, then install
            $prefix = "/themes/{$theme->slug}";
            foreach (File::allFiles($staging) as $file) {
                if (!in_array($file->getExtension(), ['html', 'xml'], true)) {
                    continue;
                }
                File::put($file->getPathname(), $this->rebase(File::get($file->getPathname()), $prefix));
            }
            $dest = "{$target}/themes/{$theme->slug}";
            File::deleteDirectory($dest);
            File::ensureDirectoryExists(dirname($dest));
            File::copyDirectory($staging, $dest);
            File::deleteDirectory($staging);

            $published[] = $theme;
            $this->info("✓ {$theme->name} → /themes/{$theme->slug}/");
        }

        if ($published !== [] && !$this->option('theme')) {
            File::put("{$target}/themes/index.html", $this->galleryIndex($published));
            $this->info('✓ gallery index → /themes/');
        }

        return self::SUCCESS;
    }

    /** Rewrite root-absolute references to live under the demo subdirectory. */
    private function rebase(string $html, string $prefix): string
    {
        // attributes: href/src/action/poster pointing at site-root paths
        $html = preg_replace(
            '#(\b(?:href|src|action|poster)=")/(?!/|themes/)#',
            '$1' . $prefix . '/',
            $html
        );
        // srcset lists: each candidate URL starting with /
        $html = preg_replace_callback('#\bsrcset="([^"]+)"#', function ($m) use ($prefix) {
            $parts = array_map(function ($candidate) use ($prefix) {
                $candidate = trim($candidate);

                return str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')
                    ? $prefix . $candidate : $candidate;
            }, explode(',', $m[1]));

            return 'srcset="' . implode(', ', $parts) . '"';
        }, $html);
        // CSS url(/...)
        $html = preg_replace('#url\((["\']?)/(?!/|themes/)#', 'url($1' . $prefix . '/', $html);

        return $html;
    }

    private function galleryIndex($themes): string
    {
        $cards = '';
        foreach ($themes as $theme) {
            $desc = e(Str::limit((string) ($theme->description ?: 'A first-party Stillopress theme.'), 120));
            $cards .= '<a class="card" href="/themes/' . e($theme->slug) . '/">'
                . '<h2>' . e($theme->name) . '</h2><p>' . $desc . '</p><span>View demo →</span></a>' . "\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Theme Demos | Stillo Press</title>
<meta name="description" content="Live demos of every first-party Stillopress theme — identical starter content rendered by each theme's design system.">
<style>
:root{color-scheme:light}
body{margin:0;font-family:'Barlow',system-ui,sans-serif;background:#f3f0ea;color:#1a1a1a}
main{max-width:1080px;margin:0 auto;padding:4rem 1.5rem}
h1{font-family:'Barlow Condensed',sans-serif;font-size:2.5rem;margin:0 0 .5rem}
.lead{color:#5c5a56;margin:0 0 3rem;max-width:60ch}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1px;background:#d8d4cb;border:1px solid #d8d4cb}
.card{display:block;background:#fbfaf7;padding:1.75rem;text-decoration:none;color:inherit}
.card:hover{background:#fff}
.card h2{margin:0 0 .5rem;font-size:1.25rem}
.card p{margin:0 0 1rem;color:#5c5a56;font-size:.925rem;line-height:1.5}
.card span{color:#c73e26;font-weight:600;font-size:.875rem}
a.back{display:inline-block;margin-bottom:2rem;color:#5c5a56;text-decoration:underline;text-underline-offset:.15em}
</style>
</head>
<body>
<main>
<a class="back" href="/">← Stillo Press</a>
<h1>Theme demos</h1>
<p class="lead">Every first-party theme, rendering the same starter site — compare typography, color, and rhythm side by side.</p>
<div class="grid">
{$cards}</div>
</main>
</body>
</html>
HTML;
    }
}
