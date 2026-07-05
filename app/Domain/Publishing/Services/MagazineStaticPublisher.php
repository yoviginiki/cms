<?php

namespace App\Domain\Publishing\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpRenderService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * W3-9 static publish (architecture rule: tenant domains serve STATIC files
 * only — never proxy to Laravel). Every published DTP issue is rendered
 * through the self-contained stillo-viewer runtime and written into the
 * publish staging tree as /magazine/{slug}-{id8}/index.html, with all CMS
 * asset URLs rewritten to static copies via AssetPublisher (which also
 * copies the files into the deploy target).
 */
class MagazineStaticPublisher
{
    public function __construct(private DtpRenderService $renderService)
    {
    }

    /** build all published DTP issues into staging; returns built count */
    public function publishForSite(\App\Models\Site $site, string $stagingPath): int
    {
        $issues = MagazineIssue::where('site_id', $site->id)
            ->where('status', 'published')
            ->get();

        $built = 0;
        foreach ($issues as $issue) {
            try {
                $data = $this->renderService->render($issue);
                if (empty($data['spreads'])) {
                    continue; // no DTP content — nothing to publish
                }

                $html = view('stillo-viewer', [
                    'issue' => $data['issue'],
                    'spreads' => $data['spreads'],
                    'pageCount' => $data['pageCount'],
                    'viewerSettings' => $issue->layout_final['viewerSettings'] ?? [],
                    'coverMode' => $data['coverMode'] ?? 'standalone',
                    'fontsUrl' => $data['fontsUrl'] ?? null,
                ])->render();

                // /api serve URLs → static /assets/files/… (copies the files too)
                $html = AssetPublisher::rewriteHtml($html);
                // /media/{site}/{asset} URLs (banners, audio tracks) → same treatment
                $html = preg_replace_callback(
                    '#(?:https?://[^/"\']+)?/media/[0-9a-f\-]{36}/([0-9a-f\-]{36})[^"\'\s)]*#i',
                    fn ($m) => AssetPublisher::resolveUrl($m[1]) ?: $m[0],
                    $html,
                );

                $dir = $this->issuePath($issue);
                File::ensureDirectoryExists("{$stagingPath}/{$dir}");
                File::put("{$stagingPath}/{$dir}/index.html", $html);
                $built++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("static magazine publish failed for issue {$issue->id}: " . $e->getMessage());
            }
        }

        return $built;
    }

    /** stable public path for an issue: magazine/{slug}-{id8} */
    public function issuePath(MagazineIssue $issue): string
    {
        $slug = Str::slug((string) $issue->title) ?: 'issue';

        return 'magazine/' . $slug . '-' . substr($issue->id, 0, 8);
    }
}
