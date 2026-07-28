<?php

namespace App\Services\SiteWizard;

use App\Domain\Sites\Services\SiteService;
use App\Jobs\SiteWizard\BuildSiteJob;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeWizard\ReferenceCaptureService;
use App\Services\ThemeWizard\ThemeVisionAnalyzer;
use App\Services\ThemeWizard\TokenProfileCompiler;
use App\Services\ThemeWizard\TokenProfileValidator;
use App\Support\SsrfGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates a whole-site build from an existing design — the integrated
 * "website creator" that chains what the Theme Wizard, Page Wizard, and site
 * services each do alone. Input is a live URL (crawled) or an uploaded design
 * ZIP; output is a complete NATIVE site: Site record, token-document Theme
 * (set active), draft Pages as real block trees, header Menu, homepage, and
 * imported media. Deterministic end to end — AI only appears in the optional
 * flag-gated theme polish step, and its failure never breaks a build.
 *
 * The pipeline is a resumable step list on the session, executed ONE step (or
 * one 3-page batch) per BuildSiteJob invocation so every run stays inside the
 * queue timeout and the SPA gets granular progress for free.
 */
class SiteWizardService
{
    /**
     * Pages built per job invocation. ONE page keeps every invocation far
     * inside the queue's retry_after window (a single extraction is capped at
     * 90s) so a slow page can never trigger a duplicate redis delivery, and
     * it gives the UI page-by-page progress.
     */
    private const PAGE_BATCH = 1;

    public function __construct(
        private SiteCrawler $crawler,
        private ZipSiteIngestor $zip,
        private UrlSiteMirror $mirror,
        private SitePageBuilder $pageBuilder,
        private SiteDocumentPageBuilder $documentBuilder,
        private SiteMenuBuilder $menuBuilder,
        private StyleProfileMapper $styleMapper,
        private TokenProfileCompiler $themeCompiler,
        private TokenProfileValidator $profileValidator,
        private SiteService $sites,
        private ReferenceCaptureService $capture,
        private ThemeVisionAnalyzer $vision,
    ) {
    }

    public function startFromUrl(User $user, string $url, array $options = []): SiteWizardSession
    {
        SsrfGuard::assertPublicHttpUrl($url); // fail fast, in the request

        $session = $this->newSession($user, 'url', $url, $options);
        BuildSiteJob::dispatch($session->id, $session->tenant_id);

        return $session;
    }

    public function startFromZip(User $user, UploadedFile $zip, array $options = []): SiteWizardSession
    {
        $session = $this->newSession($user, 'zip', null, $options);
        $this->zip->store($session, $zip);
        BuildSiteJob::dispatch($session->id, $session->tenant_id);

        return $session;
    }

    /**
     * Execute the next pending step. Returns true while more work remains —
     * the job re-dispatches itself until this returns false.
     */
    public function runStep(SiteWizardSession $session): bool
    {
        if ($session->status !== 'running') {
            return false;
        }
        $key = $session->nextPendingStep();
        if ($key === null) {
            return false;
        }

        try {
            $more = match ($key) {
                'ingest' => $this->stepIngest($session),
                'create_site' => $this->stepCreateSite($session),
                'theme' => $this->stepTheme($session),
                'polish' => $this->stepPolish($session),
                'pages' => $this->stepPages($session),
                'menu' => $this->stepMenu($session),
                'verify' => $this->stepVerify($session),
                'finalize' => $this->stepFinalize($session),
                default => false,
            };
        } catch (\Throwable $e) {
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Something went wrong during this step — you can retry the build.';
            $session->markStep($key, 'failed', mb_substr($message, 0, 300));
            $session->update(['status' => 'failed', 'error' => mb_substr($message, 0, 500)]);

            return false;
        }

        return $more || $session->refresh()->nextPendingStep() !== null;
    }

    /** Publish every built page and finish. The site is a normal site from here on. */
    public function accept(SiteWizardSession $session): Site
    {
        if ($session->status === 'accepted' && $session->site_id) {
            return Site::findOrFail($session->site_id);
        }
        if ($session->status !== 'review' || !$session->site_id) {
            throw new RuntimeException('This build is not ready to accept yet.');
        }

        Page::whereIn('id', $session->page_ids ?? [])
            ->where('status', '!=', 'published')
            ->update(['status' => 'published', 'published_at' => now()]);

        // The workspace (uploaded ZIP + extracted files) is deliberately KEPT
        // after accept so an import that turned out wrong can be diagnosed —
        // the daily site-wizard:prune sweep removes it.
        $session->update(['status' => 'accepted']);

        return Site::findOrFail($session->site_id);
    }

    /**
     * Discard everything the build created. 'new' mode: the whole site.
     * 'into' mode: only the imported pages and the submenu — the target site,
     * its theme, menu, and homepage were never the wizard's to delete.
     */
    public function abandon(SiteWizardSession $session): void
    {
        if ($session->status === 'accepted') {
            throw new RuntimeException('This build was already accepted — delete the site from the sites list instead.');
        }

        if ($session->mode() === 'into') {
            if (($session->page_ids ?? []) !== []) {
                Page::whereIn('id', $session->page_ids)->delete();
            }
            if ($session->menu_item_id) {
                // parent_id FK is null-on-delete, so remove children explicitly
                // or they'd surface as root menu items.
                \App\Models\MenuItem::where('parent_id', $session->menu_item_id)->delete();
                \App\Models\MenuItem::whereKey($session->menu_item_id)->delete();
            }
        } elseif ($session->site_id && ($site = Site::find($session->site_id))) {
            $this->sites->deleteSite($site);
        }

        $this->zip->cleanup($session);
        $session->update(['status' => 'abandoned', 'error' => null]);
    }

    /** Resume a failed build: the failed step goes back to pending, everything done stays done. */
    public function retry(SiteWizardSession $session): SiteWizardSession
    {
        if ($session->status !== 'failed') {
            throw new RuntimeException('Only a failed build can be retried.');
        }
        $steps = $session->steps ?? [];
        foreach ($steps as $i => $step) {
            if (($step['status'] ?? '') === 'failed') {
                $steps[$i]['status'] = 'pending';
                $steps[$i]['detail'] = null;
            }
        }
        $session->update(['steps' => $steps, 'status' => 'running', 'error' => null]);
        BuildSiteJob::dispatch($session->id, $session->tenant_id);

        return $session->refresh();
    }

    // ── steps ──

    private function stepIngest(SiteWizardSession $session): bool
    {
        $session->markStep('ingest', 'running');

        if ($session->source === 'url' && $session->fidelity() === 'exact') {
            // Exact copy of a live site: mirror pages + assets into the
            // workspace in ZIP layout, then reuse the whole ZIP-exact
            // pipeline (verbatim documents keep the source's own CSS/JS —
            // and with them its animations and effects).
            $mirrored = $this->mirror->mirror(
                $session,
                (string) $session->reference_url,
                (int) ($session->options['max_pages'] ?? config('cms.site_wizard.max_pages', 15)),
            );
            $entryPage = collect($mirrored['pages'])->firstWhere('is_home', true) ?? $mirrored['pages'][0];
            $entry = $this->pageBuilder->extractLocal($session, $entryPage['path']);

            $sources = array_map(fn ($p) => [
                'ref' => $p['path'],
                'slug' => $p['slug'],
                'title' => null,
                'is_home' => $p['is_home'],
                'depth' => 0,
                'page_id' => null,
                'status' => 'pending',
                'error' => null,
            ], $mirrored['pages']);

            $session->update([
                'sources' => $sources,
                'nav' => $entry['nav'],
                'style_signals' => $entry['style'],
                'title' => $this->siteName($session, $entry['manifest']['page_title'] ?? null),
            ]);
            $session->markStep('ingest', 'done', count($sources) . ' page(s) mirrored, '
                . $mirrored['stats']['files'] . ' asset file(s)');

            return true;
        }

        if ($session->source === 'url') {
            $result = $this->crawler->ingest($session);
            $sources = $result['sources'];
            // Cache the entry extraction so the pages step doesn't reload it.
            $sources[0]['manifest'] = $result['entry']['manifest'];
            $session->update([
                'sources' => $sources,
                'nav' => $result['nav'],
                'style_signals' => $result['style'],
                'title' => $this->siteName($session, $result['entry']['manifest']['page_title'] ?? null),
            ]);
        } else {
            $extracted = $this->zip->extract($session);
            $entryPage = collect($extracted['pages'])->firstWhere('is_home', true) ?? $extracted['pages'][0];
            $entry = $this->pageBuilder->extractLocal($session, $entryPage['path']);

            $sources = array_map(fn ($p) => [
                'ref' => $p['path'],
                'slug' => $p['slug'],
                'title' => null,
                'is_home' => $p['is_home'],
                'depth' => 0,
                'page_id' => null,
                'status' => 'pending',
                'error' => null,
            ], $extracted['pages']);
            foreach ($sources as $i => $source) {
                if ($source['ref'] === $entryPage['path']) {
                    $sources[$i]['manifest'] = $entry['manifest'];
                    $sources[$i]['title'] = $entry['manifest']['page_title'] ?? null;
                }
            }

            $session->update([
                'sources' => $sources,
                'nav' => $entry['nav'],
                'style_signals' => $entry['style'],
                'title' => $this->siteName($session, $entry['manifest']['page_title'] ?? null),
            ]);
        }

        $detail = count($session->refresh()->sources) . ' page(s) found';
        if ($session->source === 'url') {
            $builder = $this->detectBuilder((string) $session->reference_url);
            if ($builder !== null) {
                $session->update(['options' => array_merge($session->options ?? [], ['builder_detected' => $builder])]);
                $detail .= " — {$builder} design detected";
                if ($builder === 'Elementor') {
                    // The method doc's rule: builder JSON beats DOM heuristics.
                    // With WP database access, `elementor:import` compiles
                    // _elementor_data straight into native blocks (1:1 path).
                    $detail .= ' (with WP DB access, `elementor:import` gives a 1:1 native import)';
                }
            }
        }
        if ($session->source === 'zip' && isset($extracted['stats'])) {
            $detail .= ' in ' . $extracted['stats']['files'] . ' archive file(s)';
            if (($extracted['stats']['skipped_ext'] ?? []) !== []) {
                $detail .= ' (skipped: ' . implode(', ', array_map(
                    fn ($ext, $n) => "{$n} .{$ext}",
                    array_keys($extracted['stats']['skipped_ext']),
                    $extracted['stats']['skipped_ext'],
                )) . ')';
            }
        }
        $session->markStep('ingest', 'done', $detail);

        return true;
    }

    private function stepCreateSite(SiteWizardSession $session): bool
    {
        if ($session->mode() === 'into') {
            $session->markStep('create_site', 'skipped', 'Importing into ' . ($session->site?->name ?? 'the existing site'));

            return true;
        }

        $session->markStep('create_site', 'running');

        $tenant = Tenant::findOrFail($session->tenant_id);
        $site = $this->sites->createSite(['name' => $session->title ?: 'Imported site'], $tenant);

        // Exact-copy sites publish BARE: the package's own CSS/JS is the only
        // styling — the theme wrapper (token CSS, critical CSS, mobile
        // overrides, container width) is suppressed on every publish path,
        // including pages later rebuilt as editable block trees.
        if ($session->fidelity() === 'exact') {
            $site->update(['settings' => array_merge($site->settings ?? [], ['design_fidelity' => 'exact'])]);
        }

        $session->update(['site_id' => $site->id]);
        $session->markStep('create_site', 'done', $site->name);

        return true;
    }

    private function stepTheme(SiteWizardSession $session): bool
    {
        if ($session->mode() === 'into') {
            $session->markStep('theme', 'skipped', 'The site keeps its current theme');

            return true;
        }

        $session->markStep('theme', 'running');
        $site = Site::findOrFail($session->site_id);

        if (empty($session->style_signals)) {
            $session->markStep('theme', 'skipped', 'No style signals — using the default theme');

            return true;
        }

        $profile = $this->styleMapper->map($session->style_signals, $session->title ?: $site->name);
        $theme = $this->persistTheme($session, $site, $profile);

        $session->update(['profile' => $profile, 'theme_id' => $theme->id]);
        $session->markStep('theme', 'done', 'Colors and type read from the design');

        return true;
    }

    /**
     * Optional AI pass over a screenshot of the reference — refines the
     * deterministic theme when enabled AND credited. Any failure (no key, no
     * credits, capture problem) marks the step skipped: the deterministic
     * theme is always the committed fallback.
     */
    private function stepPolish(SiteWizardSession $session): bool
    {
        $enabled = (bool) config('cms.site_wizard.ai_polish') && (string) config('cms.ai.api_key') !== ''
            && $session->mode() === 'new';
        if (!$enabled || $session->source !== 'url' || !$session->theme_id) {
            $session->markStep('polish', 'skipped', $enabled ? 'Only available for URL imports' : 'AI polish is off');

            return true;
        }

        $session->markStep('polish', 'running');
        try {
            $image = $this->capture->fromUrl($session->reference_url);
            $result = $this->vision->analyze($session->tenant_id, $image['data'], $image['media_type']);
            $profile = $result['profile'];

            if ($this->profileValidator->validate($profile) === []) {
                $site = Site::findOrFail($session->site_id);
                $candidate = $this->themeCompiler->compile($profile);
                Theme::whereKey($session->theme_id)->update([
                    'document' => $candidate['document'],
                    'description' => $candidate['description'] ?? null,
                ]);
                $session->update([
                    'profile' => $profile,
                    'token_usage' => array_merge($session->token_usage ?? [], $result['usages']),
                ]);
            }
            $session->markStep('polish', 'done');
        } catch (\Throwable $e) {
            $session->markStep('polish', 'skipped', 'AI polish unavailable — kept the extracted theme');
        }

        return true;
    }

    private function stepPages(SiteWizardSession $session): bool
    {
        $session->markStep('pages', 'running');
        $site = Site::findOrFail($session->site_id);

        // A job killed mid-page leaves its source stuck 'building' — requeue it.
        foreach ($session->sources ?? [] as $stale) {
            if (($stale['status'] ?? '') === 'building') {
                $session->updateSource($stale['ref'], ['status' => 'pending']);
            }
        }

        $built = 0;
        while ($built < self::PAGE_BATCH) {
            $session->refresh();
            $pending = collect($session->sources ?? [])->first(fn ($s) => ($s['status'] ?? '') === 'pending');
            if ($pending === null) {
                break;
            }

            $session->updateSource($pending['ref'], ['status' => 'building']);
            try {
                // Exact fidelity (ZIP only): keep the original document verbatim
                // instead of rebuilding it as blocks.
                $result = $session->fidelity() === 'exact'
                    ? $this->documentBuilder->build($session, $site, $pending)
                    : $this->pageBuilder->build($session, $site, $pending);
                $session->updateSource($pending['ref'], [
                    'status' => 'done',
                    'page_id' => $result['page']->id,
                    'title' => $result['title'],
                    'manifest' => null, // cached entry manifest is spent — keep the row small
                ]);
                $session->update(['page_ids' => array_merge($session->refresh()->page_ids ?? [], [$result['page']->id])]);

                // Depth-1 pages can reveal pages the entry didn't link (up to the cap).
                if ($session->source === 'url' && ($pending['depth'] ?? 0) === 1 && $result['links'] !== []) {
                    $extended = $this->crawler->extendFrontier($session, $session->refresh()->sources, $result['links'], 1);
                    $session->update(['sources' => $extended]);
                }
            } catch (\Throwable $e) {
                // A single unreadable page must not sink the site.
                $message = $e instanceof RuntimeException ? $e->getMessage() : 'Could not build this page.';
                $session->updateSource($pending['ref'], ['status' => 'failed', 'error' => mb_substr($message, 0, 200)]);
            }
            $built++;
        }

        $session->refresh();
        $remaining = collect($session->sources ?? [])->contains(fn ($s) => in_array($s['status'] ?? '', ['pending', 'building'], true));
        if ($remaining) {
            return true; // step stays 'running'; the next job invocation continues the batch
        }

        $done = collect($session->sources)->where('status', 'done')->count();
        if ($done === 0) {
            throw new RuntimeException('None of the pages could be built.');
        }
        if ($session->fidelity() === 'exact') {
            // Every page (and thus its final slug) now exists — resolve the
            // cross-page link tokens the documents carry.
            $this->documentBuilder->finalizeLinks($session);
        }
        $session->markStep('pages', 'done', "{$done} of " . count($session->sources) . ' pages built'
            . ($session->fidelity() === 'exact' ? ' (exact copy)' : ''));

        return true;
    }

    private function stepMenu(SiteWizardSession $session): bool
    {
        $session->markStep('menu', 'running');
        $site = Site::findOrFail($session->site_id);

        if ($session->mode() === 'into') {
            $result = $this->menuBuilder->buildInto($session, $site);
            if ($result) {
                $session->update(['menu_id' => $result['menu']->id, 'menu_item_id' => $result['parent']->id]);
                $count = \App\Models\MenuItem::where('parent_id', $result['parent']->id)->count();
                $session->markStep('menu', 'done', "“{$result['parent']->label}” submenu with {$count} item(s)");
            } else {
                $session->markStep('menu', 'skipped', 'No navigation could be built');
            }

            return true;
        }

        // Exact-copy designs ship their own <header> nav inside every page —
        // a CMS menu would render a SECOND header on top of it.
        if ($session->fidelity() === 'exact') {
            $session->markStep('menu', 'skipped', 'The design ships its own navigation');

            return true;
        }

        $menu = $this->menuBuilder->build($session, $site);
        if ($menu) {
            $session->update(['menu_id' => $menu->id]);
            $session->markStep('menu', 'done', $menu->items()->count() . ' menu items');
        } else {
            $session->markStep('menu', 'skipped', 'No navigation could be built');
        }

        return true;
    }

    /**
     * The measurement half of the design-recreation method, built into the
     * pipeline (docs/DESIGN-RECREATION-METHOD.md §3): every build ends with an
     * OBJECTIVE fidelity score, not an eyeball. Each built page is rendered
     * in-process (they are still drafts — no live URL exists) and compared
     * against the source HTML with the migration diff engine; per-page
     * coverage + gap counts land on the source row for the UI, the roll-up in
     * the step detail. Batched like stepPages so slow origins can't blow the
     * queue timeout, and never fails the build — a score is advice, not a gate.
     */
    private function stepVerify(SiteWizardSession $session): bool
    {
        if ($session->fidelity() === 'exact') {
            $session->markStep('verify', 'skipped', 'Exact copies are verbatim by construction');

            return true;
        }

        $session->markStep('verify', 'running');
        $site = Site::findOrFail($session->site_id);
        $checker = app(\App\Domain\Migration\Services\MigrationDiffChecker::class);
        $renderer = app(\App\Domain\Publishing\Services\BuildPageService::class);
        $originHost = $session->source === 'url'
            ? (string) (parse_url((string) $session->reference_url, PHP_URL_HOST) ?: '')
            : '';

        $needsCheck = fn ($s) => ($s['status'] ?? '') === 'done'
            && !empty($s['page_id']) && !array_key_exists('fidelity', $s);

        $checked = 0;
        while ($checked < self::PAGE_BATCH) {
            $session->refresh();
            $pending = collect($session->sources ?? [])->first($needsCheck);
            if ($pending === null) {
                break;
            }
            $session->updateSource($pending['ref'], [
                'fidelity' => $this->measureFidelity($session, $site, $checker, $renderer, $originHost, $pending),
            ]);
            $checked++;
        }

        if (collect($session->refresh()->sources ?? [])->contains($needsCheck)) {
            return true; // step stays 'running'; the next job invocation continues
        }

        $scores = collect($session->sources ?? [])
            ->pluck('fidelity')->filter(fn ($f) => isset($f['coverage']))->values();
        if ($scores->isEmpty()) {
            $session->markStep('verify', 'skipped', 'No pages could be compared against the source');

            return true;
        }

        $avg = round((float) $scores->avg('coverage'), 1);
        $withGaps = $scores->filter(fn ($f) => ($f['missing_headings'] ?? 0) + ($f['missing_images'] ?? 0) > 0)->count();
        $session->markStep('verify', 'done', "Text coverage {$avg}% across {$scores->count()} page(s)"
            . ($withGaps > 0 ? " — {$withGaps} with missing headings or images" : ' — no content gaps found'));

        return true;
    }

    /** @return array per-page fidelity scores, or ['error' => …] — never throws */
    private function measureFidelity(
        SiteWizardSession $session,
        Site $site,
        \App\Domain\Migration\Services\MigrationDiffChecker $checker,
        \App\Domain\Publishing\Services\BuildPageService $renderer,
        string $originHost,
        array $source,
    ): array {
        try {
            if ($session->source === 'url') {
                // Cache-bust: edge caches serve hours-stale HTML otherwise.
                $sep = str_contains($source['ref'], '?') ? '&' : '?';
                $originHtml = \Illuminate\Support\Facades\Http::timeout(25)->retry(1, 300)
                    ->get($source['ref'] . $sep . '_wfid=' . time())->throw()->body();
            } else {
                if (!is_file($source['ref'])) {
                    throw new RuntimeException('The extracted source file is gone');
                }
                $originHtml = (string) file_get_contents($source['ref']);
            }

            $page = Page::where('site_id', $site->id)->findOrFail($source['page_id']);
            $newHtml = $renderer->build($page, $site->theme, $site);
            $report = $checker->compareHtml($site, $originHost, $originHtml, $newHtml, $source['slug'] ?? $source['ref']);

            return [
                'coverage' => $report['text_coverage'],
                'missing_headings' => count($report['missing_headings']),
                'missing_images' => count($report['missing_images']),
                'missing_links' => count($report['missing_links']),
                'gap_samples' => array_slice(array_merge(
                    $report['missing_headings'],
                    $report['missing_text_samples'] ?? [],
                ), 0, 3),
            ];
        } catch (\Throwable $e) {
            return ['error' => mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /** Best-effort builder fingerprint of the reference site (never throws). */
    private function detectBuilder(string $url): ?string
    {
        try {
            $html = \Illuminate\Support\Facades\Http::timeout(15)->get($url)->body();
        } catch (\Throwable) {
            return null;
        }

        return match (true) {
            str_contains($html, 'elementor') => 'Elementor',
            str_contains($html, 'et_pb_') || str_contains($html, 'Divi') => 'Divi',
            str_contains($html, 'wp-content') => 'WordPress',
            default => null,
        };
    }

    private function stepFinalize(SiteWizardSession $session): bool
    {
        $session->markStep('finalize', 'running');
        $site = Site::findOrFail($session->site_id);

        // 'into' mode never touches the target site's homepage.
        if ($session->mode() === 'new') {
            $home = collect($session->sources ?? [])->first(fn ($s) => ($s['is_home'] ?? false) && !empty($s['page_id']))
                ?? collect($session->sources ?? [])->first(fn ($s) => !empty($s['page_id']));
            if ($home) {
                $site->update(['settings' => array_merge($site->settings ?? [], [
                    'homepage_id' => $home['page_id'],
                    'homepage_type' => 'page',
                ])]);
            }
        }

        $session->markStep('finalize', 'done');
        $session->update(['status' => 'review']);

        return false;
    }

    // ── internals ──

    private function newSession(User $user, string $source, ?string $url, array $options): SiteWizardSession
    {
        // A target site switches the wizard to 'into' mode: pages + a submenu
        // are added to that site; its theme/menu/homepage stay untouched.
        $targetSite = null;
        if (!empty($options['site_id'])) {
            $targetSite = Site::where('tenant_id', $user->tenant_id)->findOrFail($options['site_id']);
        }

        return SiteWizardSession::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'site_id' => $targetSite?->id,
            'status' => 'running',
            'source' => $source,
            'reference_url' => $url,
            'options' => [
                'mode' => $targetSite ? 'into' : 'new',
                'max_pages' => (int) ($options['max_pages'] ?? config('cms.site_wizard.max_pages', 15)),
                'name' => $options['name'] ?? null,
                'menu_label' => isset($options['menu_label']) ? mb_substr(trim((string) $options['menu_label']), 0, 60) : null,
                // ZIP design packages default to a pixel-perfect copy; 'blocks'
                // rebuilds them as native editable pages instead.
                'fidelity' => in_array($options['fidelity'] ?? null, ['exact', 'blocks'], true)
                    ? $options['fidelity']
                    : ($source === 'zip' ? 'exact' : 'blocks'),
            ],
            'steps' => SiteWizardSession::seedSteps(),
        ]);
    }

    /** Same persistence as ThemeWizardService::accept — a real, editable site theme. */
    private function persistTheme(SiteWizardSession $session, Site $site, array $profile): Theme
    {
        $candidate = $this->themeCompiler->compile($profile);

        $theme = new Theme();
        $theme->fill([
            'site_id' => $site->id,
            'name' => $candidate['name'] ?? 'Imported theme',
            'slug' => $candidate['slug'] ?? Str::slug('imported-' . Str::lower(Str::random(4))),
            'version' => '1.0.0',
            'description' => $candidate['description'] ?? null,
            'config' => [],
            'template_path' => '',
            'manifest_json' => ['author' => 'Site Wizard', 'wizard_session_id' => $session->id],
            'document' => $candidate['document'],
            'modes' => ['light'],
            'schema_version' => '1.0.0',
        ]);
        $theme->save();

        $site->update(['active_theme_id' => $theme->id]);

        return $theme;
    }

    /** Site name: explicit option → cleaned page <title> → host. */
    private function siteName(SiteWizardSession $session, ?string $pageTitle): string
    {
        $explicit = trim((string) ($session->options['name'] ?? ''));
        if ($explicit !== '') {
            return mb_substr($explicit, 0, 120);
        }

        $title = trim((string) $pageTitle);
        // "Home — Acme Studio" / "Acme Studio | Welcome" → keep the brandiest half.
        if ($title !== '' && preg_match('/[|\x{2013}\x{2014}-]/u', $title)) {
            $parts = array_map('trim', preg_split('/[|\x{2013}\x{2014}]|\s-\s/u', $title) ?: []);
            $parts = array_filter($parts, fn ($p) => $p !== '' && !preg_match('/^(home|welcome|start)$/i', $p));
            if ($parts !== []) {
                usort($parts, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
                $title = $parts[0];
            }
        }
        if ($title !== '') {
            return mb_substr($title, 0, 120);
        }

        $host = (string) parse_url((string) $session->reference_url, PHP_URL_HOST);

        return $host !== '' ? $host : 'Imported site';
    }
}
