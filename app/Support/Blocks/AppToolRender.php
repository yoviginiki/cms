<?php

namespace App\Support\Blocks;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * Publishes the shared, self-hosted runtime (JS + CSS) for the interactive
 * app-blocks — breathing pacer, meditation timer, pelvic trainer, partner deck.
 *
 * Same convention as SliderRender::publishRuntime / CollectionPublishService:
 * content-hash the source so the filename busts caches, copy into the build's
 * deploy target (the staging tree — NOT the live docroot, which the symlink
 * swap would drop) AND the CMS public dir (for the preview iframe / dynamic
 * render on the Laravel origin). Returns root-absolute /assets URLs;
 * BuildPageService::rewriteBaseForSlugHosting prefixes the slug for
 * subdirectory-hosted sites.
 */
class AppToolRender
{
    /** Block types whose presence on a page requires the runtime. */
    public const TYPES = ['breathing-pacer', 'meditation-timer', 'pelvic-trainer', 'partner-deck'];

    /**
     * @return array{js: string, css: string}
     */
    public static function publishRuntime(Site $site): array
    {
        $jsSource = resource_path('js/app-tools.js');
        $cssSource = resource_path('js/app-tools.css');
        $jsHash = substr(md5_file($jsSource), 0, 8);
        $cssHash = substr(md5_file($cssSource), 0, 8);

        $files = [
            ["app-tools.{$jsHash}.js", $jsSource],
            ["app-tools.{$cssHash}.css", $cssSource],
        ];

        // Deploy target = the atomic staging tree for this build (set by
        // PublishSiteJob). Fall back to the live docroot only for ad-hoc
        // dynamic renders where no build is staged.
        $target = AssetPublisher::deployTarget()
            ?: rtrim((string) config('publishing.public_path'), '/') . '/' . $site->slug;

        foreach (array_filter([$target, public_path()]) as $base) {
            try {
                File::ensureDirectoryExists("{$base}/assets");
                foreach ($files as [$name, $source]) {
                    if (!file_exists("{$base}/assets/{$name}")) {
                        File::copy($source, "{$base}/assets/{$name}");
                        @chmod("{$base}/assets/{$name}", 0664);
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning("app-tools runtime publish failed ({$base}) for site {$site->id}: {$e->getMessage()}");
            }
        }

        return [
            'js' => "/assets/app-tools.{$jsHash}.js",
            'css' => "/assets/app-tools.{$cssHash}.css",
        ];
    }
}
