<?php

namespace App\Domain\Library\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Models\Block;
use App\Models\BlockTemplate;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Renders a Library item's block tree to a static thumbnail PNG (Builder P1
 * Slice E). The item is a detached block-tree JSON; to render it faithfully we
 * reuse the real publish path (BuildPageService::renderBlock), which needs
 * DB-backed blocks — so the tree is materialised inside a transaction and rolled
 * back, leaving nothing behind. The HTML is wrapped in the site's design tokens
 * so `var(--…)` values resolve, then screenshotted via Playwright.
 *
 * Storage mirrors AssetServeController: PNG on the `assets` disk, streamed by a
 * public route. Degrades gracefully — capture() returns false (and the item
 * keeps its client wireframe fallback) whenever Playwright/proc_open is absent.
 */
class LibraryThumbnailService
{
    private const DIR = 'library-thumbs';
    private const W = 1200;
    private const H = 800;

    public function __construct(
        private BlockService $blocks,
        private BuildPageService $builder,
        private DesignTokenGenerator $tokens,
    ) {}

    /** Whether server-side screenshotting can run in this PHP context. */
    public function available(): bool
    {
        if (!function_exists('proc_open')) return false;
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Generate + store a thumbnail for a library item, rendered in the given
     * site's theme context. Returns the public URL, or null on failure.
     */
    public function generateFor(BlockTemplate $item, Site $site): ?string
    {
        if (!$this->available()) return null;

        $tree = is_array($item->blocks_data) ? $item->blocks_data : [];
        if ($tree === []) return null;

        try {
            $html = $this->renderHtml($tree, $site);
        } catch (\Throwable $e) {
            Log::warning('LibraryThumbnail: render failed', ['id' => $item->id, 'err' => mb_substr($e->getMessage(), 0, 200)]);
            return null;
        }

        $png = $this->capture($html);
        if ($png === null) return null;

        $path = self::DIR . '/' . $item->id . '.png';
        Storage::disk('assets')->put($path, $png);

        return $this->urlFor($item->id);
    }

    /** Public URL the admin <img> loads (served by LibraryThumbnailController). */
    public function urlFor(string $id): string
    {
        // no extension — nginx would serve .png statically before Laravel
        return "/library-thumbnails/{$id}";
    }

    /**
     * Materialise the detached tree under a throwaway page, render it via the
     * real publish path, wrap in the site's tokens, and roll everything back.
     */
    public function renderHtml(array $tree, Site $site): string
    {
        DB::beginTransaction();
        try {
            $page = Page::create([
                'site_id' => $site->id,
                'title' => '__thumb',
                'slug' => '__thumb_' . Str::random(10),
                'status' => 'draft',
            ]);
            $this->blocks->syncBlocks($page, $tree);

            $roots = Block::where('blockable_type', $page->getMorphClass())
                ->where('blockable_id', $page->id)
                ->whereNull('parent_block_id')
                ->orderBy('order')->get();

            $body = '';
            foreach ($roots as $root) {
                $body .= $this->builder->renderBlock($root, $site);
            }
            $css = $this->tokens->generate($site);
        } finally {
            DB::rollBack();
        }

        return "<!doctype html><html><head><meta charset=\"utf-8\">"
            . "<style>{$css}</style>"
            . "<style>html,body{margin:0;padding:0;background:var(--color-bg,#fff);overflow:hidden}*{animation:none!important;transition:none!important}</style>"
            . "</head><body>{$body}</body></html>";
    }

    /** Screenshot the HTML via Playwright; returns raw PNG bytes or null. */
    public function capture(string $html): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'libthumb_') . '.html';
        file_put_contents($tmp, $html);

        try {
            $node = trim((string) (config('cms.theme_wizard.node_bin') ?? 'node'));
            $proc = new Process([$node, base_path('scripts/capture-html.mjs'), $tmp, self::W . 'x' . self::H], base_path());
            $proc->setTimeout(60);
            $proc->run();

            if (!$proc->isSuccessful()) {
                Log::warning('LibraryThumbnail: capture failed', ['err' => mb_substr(trim($proc->getErrorOutput()), 0, 200)]);
                return null;
            }
            $b64 = trim($proc->getOutput());
            $bytes = base64_decode($b64, true);
            return ($bytes === false || $bytes === '') ? null : $bytes;
        } finally {
            @unlink($tmp);
        }
    }
}
