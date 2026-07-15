<?php

namespace App\Services\PageWizard;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Jobs\PageWizard\CapturePageJob;
use App\Models\Page;
use App\Models\PageWizard\PageWizardSession;
use App\Models\Site;
use App\Models\User;
use App\Services\ThemeWizard\ReferenceCaptureService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Orchestrates a Page Wizard session end to end. Every path converges on a
 * real DRAFT page (created on first generation, refined in place by nudges),
 * so the wizard's output is always a normal editable page — never a parallel
 * format. Accept keeps it; abandon deletes it.
 */
class PageWizardService
{
    public function __construct(
        private PageWizardEngine $engine,
        private PageManifestCompiler $compiler,
        private PageReferenceFetcher $fetcher,
        private ReferenceCaptureService $capture,
        private PageService $pages,
        private BlockService $blocks,
    ) {
    }

    /**
     * Start from a URL. mode='layout' screenshots the page (needs proc_open →
     * queued worker); mode='content' fetches + reads its HTML (runs inline).
     */
    public function startFromUrl(Site $site, User $user, string $url, string $mode, ?string $hint = null): PageWizardSession
    {
        $mode = in_array($mode, ['layout', 'content'], true) ? $mode : 'layout';

        if ($mode === 'content') {
            $content = $this->fetcher->fetch($url); // SSRF-gated
            $result = $this->engine->fromContent($site->tenant_id, $content, $hint);

            return $this->finalize(
                $this->newSession($site, $user, 'url', 'content', $url, "Build a page from the content of {$url}"),
                $result['manifest'],
                $result['usages'],
            );
        }

        // Layout: screenshot on the queue worker, poll from the UI.
        $session = $this->newSession($site, $user, 'url', 'layout', $url, "Rebuild the layout of {$url}", 'capturing');
        CapturePageJob::dispatch($session->id, $site->tenant_id, $hint);

        return $session;
    }

    /** Queue-worker side of the layout-from-URL path: screenshot → vision → draft. */
    public function completeUrlCapture(PageWizardSession $session, ?string $hint = null): void
    {
        if ($session->status !== 'capturing') {
            return;
        }
        try {
            // Full-page capture: layout replication needs the WHOLE page, not
            // just the top viewport, or the model only reconstructs the header.
            $image = $this->capture->fromUrl($session->reference_url, fullPage: true);
            $result = $this->engine->fromScreenshot($session->tenant_id, $image, $hint);
            $this->finalize($session, $result['manifest'], $result['usages']);
        } catch (\Throwable $e) {
            $session->update([
                'status' => 'capture_failed',
                'error' => $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Could not read that page — try uploading a screenshot instead.',
            ]);
        }
    }

    public function startFromUpload(Site $site, User $user, UploadedFile $file, ?string $hint = null): PageWizardSession
    {
        $image = $this->capture->fromUpload($file); // validates + base64, no proc_open
        $result = $this->engine->fromScreenshot($site->tenant_id, $image, $hint);

        return $this->finalize(
            $this->newSession($site, $user, 'upload', 'layout', null, 'Build a page from this screenshot'),
            $result['manifest'],
            $result['usages'],
        );
    }

    public function startFromDescription(Site $site, User $user, string $description): PageWizardSession
    {
        $result = $this->engine->fromDescription($site->tenant_id, $description);

        return $this->finalize(
            $this->newSession($site, $user, 'describe', 'describe', null, trim($description)),
            $result['manifest'],
            $result['usages'],
        );
    }

    /** Conversational refinement — re-generates the manifest and re-syncs the draft page. */
    public function nudge(PageWizardSession $session, string $instruction): PageWizardSession
    {
        if (!in_array($session->status, ['drafting'], true)) {
            throw new RuntimeException('This page has already been accepted.');
        }
        if (empty($session->manifest)) {
            throw new RuntimeException('There is no draft to refine yet.');
        }

        $result = $this->engine->nudge($session->tenant_id, $session->manifest, $instruction);

        $transcript = $session->transcript ?? [];
        $transcript[] = ['role' => 'user', 'text' => $instruction, 'at' => now()->toIso8601String()];

        return $this->finalize(
            $session,
            $result['manifest'],
            array_merge($session->token_usage ?? [], $result['usages']),
            $transcript,
        );
    }

    /** Keep the draft page (optionally publish it). It's a normal page from here on. */
    public function accept(PageWizardSession $session, bool $publish = false): Page
    {
        if ($session->status === 'accepted' && $session->page_id) {
            return Page::findOrFail($session->page_id);
        }
        $page = $session->page_id ? Page::find($session->page_id) : null;
        if (!$page) {
            throw new RuntimeException('There is no page to save yet.');
        }
        if ($publish) {
            $page->update(['status' => 'published', 'published_at' => $page->published_at ?? now()]);
        }
        $session->update(['status' => 'accepted']);

        return $page;
    }

    public function abandon(PageWizardSession $session): void
    {
        if ($session->page_id && $session->status !== 'accepted') {
            Page::where('id', $session->page_id)->delete();
        }
        $session->update(['status' => 'abandoned', 'page_id' => null]);
    }

    // ── internals ──

    private function newSession(Site $site, User $user, string $source, string $mode, ?string $url, string $openingLine, string $status = 'drafting'): PageWizardSession
    {
        return PageWizardSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'user_id' => $user->id,
            'title' => 'New page',
            'status' => $status,
            'source' => $source,
            'mode' => $mode,
            'reference_url' => $url,
            'transcript' => [['role' => 'user', 'text' => $openingLine, 'at' => now()->toIso8601String()]],
        ]);
    }

    /**
     * Compile the manifest → block tree, create-or-reuse the draft page,
     * sync the blocks onto it, and move the session to `drafting`.
     */
    private function finalize(PageWizardSession $session, array $manifest, array $usages, ?array $transcript = null): PageWizardSession
    {
        $tree = $this->compiler->compile($manifest);
        if ($tree === []) {
            throw new RuntimeException('The generated page had no usable blocks — try rephrasing.');
        }

        $title = trim((string) ($manifest['page_title'] ?? '')) ?: 'New page';

        $page = $session->page_id ? Page::find($session->page_id) : null;
        if (!$page) {
            $page = $this->pages->createPage([
                'title' => mb_substr($title, 0, 255),
                'status' => 'draft',
            ], $session->site);
        } else {
            $page->update(['title' => mb_substr($title, 0, 255)]);
        }

        $this->blocks->syncBlocks($page, $tree);

        $transcript ??= $session->transcript ?? [];
        $transcript[] = ['role' => 'assistant', 'text' => $manifest['design_read'] ?? 'Here is your page.', 'at' => now()->toIso8601String()];

        $session->update([
            'title' => $title,
            'status' => 'drafting',
            'manifest' => $manifest,
            'page_id' => $page->id,
            'transcript' => $transcript,
            'token_usage' => $usages,
            'error' => null,
        ]);

        return $session->refresh();
    }
}
