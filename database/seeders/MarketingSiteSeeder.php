<?php

namespace Database\Seeders;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingSiteSeeder extends Seeder
{
    private int $rowOrder = 0;
    private int $colOrder = 0;
    private int $blockOrder = 0;

    public function __construct(
        private PageService $pageService,
        private BlockService $blockService,
    ) {}

    public function run(): void
    {
        DB::statement("SET app.current_tenant_id = '019dfba5-a96b-719d-954d-60a4a549f949'");
        $site = Site::findOrFail('019f1d72-4f89-73e9-984e-707c32b12fb1');

        // Delete old demos page if it exists
        $oldDemos = $site->pages()->where('slug', 'demos')->first();
        if ($oldDemos) {
            $oldDemos->blocks()->delete();
            $oldDemos->forceDelete();
            $this->command->info("  Removed old: Demos (replaced by Examples)");
        }

        $pages = [
            $this->homePage(),
            $this->featuresPage(),
            $this->aboutPage(),
            $this->examplesPage(),
            $this->pricingPage(),
            $this->docsPage(),
            $this->contactPage(),
        ];

        foreach ($pages as $def) {
            $this->resetCounters();
            $page = $site->pages()->where('slug', $def['slug'])->first();
            if (!$page) {
                $page = $this->pageService->createPage([
                    'title' => $def['title'],
                    'slug' => $def['slug'],
                    'status' => 'published',
                ], $site);
                $this->command->info("  Created: {$def['title']}");
            } else {
                $page->update(['title' => $def['title']]);
                $this->command->info("  Updated: {$def['title']}");
            }
            $this->blockService->syncBlocks($page, $def['blocks']);
        }

        // Set homepage
        $home = $site->pages()->where('slug', 'home')->first();
        if ($home) {
            $settings = $site->settings ?? [];
            $settings['homepage_id'] = $home->id;
            $settings['homepage_type'] = 'page';
            $site->update(['settings' => $settings]);
        }
    }

    // ─── Page definitions ───────────────────────────────────────────────

    private function homePage(): array
    {
        return [
            'title' => 'Stillopress',
            'slug' => 'home',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="padding:clamp(5rem,12vh,9rem) 0 clamp(2rem,4vh,3rem);max-width:1320px;margin:0 auto;padding-inline:clamp(1.25rem,4vw,4.5rem)">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:600;font-size:.78rem;color:var(--color-text-muted);margin:0 0 1.8rem;display:flex;align-items:center;gap:.7em"><span style="width:26px;height:1px;background:var(--color-primary);display:inline-block"></span> Build. Publish. Own.</p>
  <h1 style="font-family:var(--font-heading);font-weight:600;font-size:clamp(2.8rem,8vw,6rem);line-height:.92;letter-spacing:-.02em;margin:0 0 1.8rem;max-width:18ch">Your website should belong to you.</h1>
  <p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:48ch;margin:0 0 2.4rem">Build in a calm visual studio. Publish a fast, durable website made of ordinary files you can host, move, archive, and keep.</p>
  <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2.4rem">
    <a href="/pricing" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.9rem;padding:.9em 1.4em;background:var(--color-text);color:var(--color-bg);border:1px solid var(--color-text);text-decoration:none;transition:background .25s,color .25s">Start building free →</a>
    <a href="/examples" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.9rem;padding:.9em 1.4em;background:transparent;color:var(--color-text);border:1px solid var(--color-border);text-decoration:none;transition:background .25s,color .25s">Explore examples</a>
  </div>
  <div style="display:flex;gap:clamp(1.5rem,3vw,2.5rem);flex-wrap:wrap;font-family:var(--font-heading);font-size:.74rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-text-muted)">
    <span>No plugins</span><span style="color:var(--color-border)">·</span>
    <span>Export anytime</span><span style="color:var(--color-border)">·</span>
    <span>Static by default</span><span style="color:var(--color-border)">·</span>
    <span>Built for speed</span>
  </div>
</div>']),
                            // Editor mockup
                            $this->block('html-embed', ['html' => '<div style="max-width:1320px;margin:0 auto;padding:0 clamp(1.25rem,4vw,4.5rem) clamp(2rem,4vh,3rem)"><div style="border:1px solid var(--color-border);overflow:hidden;background:var(--color-bg-alt)"><div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem 1rem;border-bottom:1px solid var(--color-border);background:var(--color-bg)"><div style="display:flex;align-items:center;gap:.6rem"><div style="width:8px;height:8px;border-radius:50%;background:var(--color-primary)"></div><span style="font-family:var(--font-heading);font-size:.8rem;font-weight:600;letter-spacing:.02em">Stillopress</span></div><div style="display:flex;gap:.5rem;align-items:center"><span style="font-family:var(--font-heading);font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted);padding:.3em .8em;border:1px solid var(--color-border)">Preview</span><span style="font-family:var(--font-heading);font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-bg);background:var(--color-text);padding:.3em .8em">Publish</span></div></div><div style="display:grid;grid-template-columns:180px 1fr 200px;min-height:340px" class="editor-mock"><div style="border-right:1px solid var(--color-border);padding:1.2rem .8rem;background:color-mix(in srgb,var(--color-bg-alt) 50%,var(--color-bg))"><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.6rem;color:var(--color-text-muted);margin-bottom:.8rem">Pages</div><div style="padding:.35rem .5rem;font-size:.76rem;background:var(--color-bg);border:1px solid var(--color-border);margin-bottom:.3rem;font-weight:500">The Quarterly</div><div style="padding:.35rem .5rem;font-size:.76rem;color:var(--color-text-muted)">About</div><div style="padding:.35rem .5rem;font-size:.76rem;color:var(--color-text-muted)">Work</div><div style="padding:.35rem .5rem;font-size:.76rem;color:var(--color-text-muted)">Contact</div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.6rem;color:var(--color-text-muted);margin:1.2rem 0 .8rem">Blocks</div><div style="padding:.3rem .5rem;font-size:.72rem;color:var(--color-text-muted);border:1px solid var(--color-border);margin-bottom:.25rem">Section</div><div style="padding:.3rem .5rem;font-size:.72rem;color:var(--color-text-muted);border:1px solid var(--color-border);margin-bottom:.25rem">Heading</div><div style="padding:.3rem .5rem;font-size:.72rem;color:var(--color-text-muted);border:1px solid var(--color-border);margin-bottom:.25rem">Image</div><div style="padding:.3rem .5rem;font-size:.72rem;color:var(--color-text-muted);border:1px solid var(--color-border);margin-bottom:.25rem">Gallery</div></div><div style="padding:2rem 2.5rem;background:var(--color-bg)"><div style="font-family:var(--font-heading);font-size:clamp(1.4rem,2vw,2rem);font-weight:600;margin-bottom:.8rem;color:var(--color-heading)">The Quarterly</div><div style="width:60px;height:1px;background:var(--color-primary);margin-bottom:1rem"></div><p style="font-size:.88rem;line-height:1.6;color:var(--color-text-muted);max-width:36ch;margin:0 0 1.5rem">A journal of ideas, work, and perspective. Published quarterly from the studio.</p><div style="width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 40%,var(--color-bg-alt)));display:flex;align-items:center;justify-content:center;border:1px solid var(--color-border)"><span style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.15em;color:var(--color-text-muted)">Featured image</span></div></div><div style="border-left:1px solid var(--color-border);padding:1.2rem .8rem;background:color-mix(in srgb,var(--color-bg-alt) 50%,var(--color-bg))"><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.6rem;color:var(--color-text-muted);margin-bottom:.8rem">Settings</div><div style="font-size:.68rem;color:var(--color-text-muted);margin-bottom:.3rem">Font family</div><div style="height:5px;background:var(--color-border);width:85%;margin-bottom:.8rem"></div><div style="font-size:.68rem;color:var(--color-text-muted);margin-bottom:.3rem">Font size</div><div style="height:5px;background:var(--color-border);width:60%;margin-bottom:.8rem"></div><div style="font-size:.68rem;color:var(--color-text-muted);margin-bottom:.3rem">Alignment</div><div style="display:flex;gap:.2rem;margin-bottom:.8rem"><div style="width:18px;height:18px;border:1px solid var(--color-border)"></div><div style="width:18px;height:18px;border:1px solid var(--color-primary);background:color-mix(in srgb,var(--color-primary) 10%,transparent)"></div><div style="width:18px;height:18px;border:1px solid var(--color-border)"></div></div><div style="font-size:.68rem;color:var(--color-text-muted);margin-bottom:.3rem">Spacing</div><div style="height:5px;background:var(--color-border);width:45%;margin-bottom:.8rem"></div><div style="font-size:.68rem;color:var(--color-text-muted);margin-bottom:.3rem">Theme</div><div style="display:flex;gap:.25rem;margin-bottom:.8rem"><div style="width:14px;height:14px;background:var(--color-text);border:1px solid var(--color-text)"></div><div style="width:14px;height:14px;background:var(--color-bg);border:1px solid var(--color-border)"></div><div style="width:14px;height:14px;background:var(--color-bg-alt);border:1px solid var(--color-border)"></div></div></div></div></div><style>@media(max-width:760px){.editor-mock{grid-template-columns:1fr !important;min-height:auto !important}.editor-mock>div:first-child,.editor-mock>div:last-child{display:none}}</style>']),
                            // Proof row is now inline in the hero html-embed above
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '100%']),

                // Section 2: Product Editor
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— The editor</p>']),
                            $this->block('heading', ['text' => 'A studio for making pages, not managing software.', 'level' => 'h2', 'fontSize' => 'clamp(1.9rem,4.2vw,3.4rem)']),
                            $this->block('paragraph', ['content' => '<p style="max-width:52ch;color:var(--color-text-muted)">Write, arrange, refine, and publish in one focused space. Stillopress keeps the complexity behind the scenes so your attention stays on the page.</p>']),
                            $this->block('spacer', ['height' => '40px']),
                            $this->block('html-embed', ['html' => '<div style="aspect-ratio:16/10;background:var(--color-bg-alt);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;overflow:hidden">
  <div style="display:grid;grid-template-columns:200px 1fr 240px;width:100%;height:100%">
    <div style="background:color-mix(in srgb,var(--color-bg-alt) 60%,var(--color-bg));border-right:1px solid var(--color-border);padding:1.5rem 1rem">
      <div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.65rem;color:var(--color-text-muted);margin-bottom:1rem">Blocks</div>
      <div style="display:flex;flex-direction:column;gap:.4rem">
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Heading</div>
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Paragraph</div>
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Image</div>
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Gallery</div>
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Section</div>
        <div style="padding:.4rem .6rem;font-size:.78rem;color:var(--color-text-muted);border:1px solid var(--color-border)">Button</div>
      </div>
    </div>
    <div style="padding:2rem 3rem;display:flex;flex-direction:column;gap:1.5rem">
      <div style="font-family:var(--font-heading);font-size:1.8rem;font-weight:600;color:var(--color-heading)">Page title here</div>
      <div style="height:1px;background:var(--color-border);width:60px"></div>
      <div style="color:var(--color-text-muted);font-size:.9rem;line-height:1.6;max-width:40ch">Body content with structured blocks. Each element is typed, portable, and themeable.</div>
      <div style="width:100%;aspect-ratio:16/9;background:var(--color-border);opacity:.3"></div>
    </div>
    <div style="background:color-mix(in srgb,var(--color-bg-alt) 60%,var(--color-bg));border-left:1px solid var(--color-border);padding:1.5rem 1rem">
      <div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.65rem;color:var(--color-text-muted);margin-bottom:1rem">Settings</div>
      <div style="display:flex;flex-direction:column;gap:.6rem">
        <div style="font-size:.72rem;color:var(--color-text-muted)">Font size</div>
        <div style="height:6px;background:var(--color-border);width:80%"></div>
        <div style="font-size:.72rem;color:var(--color-text-muted);margin-top:.5rem">Alignment</div>
        <div style="display:flex;gap:.3rem"><div style="width:20px;height:20px;border:1px solid var(--color-border)"></div><div style="width:20px;height:20px;border:1px solid var(--color-border)"></div><div style="width:20px;height:20px;border:1px solid var(--color-border)"></div></div>
        <div style="font-size:.72rem;color:var(--color-text-muted);margin-top:.5rem">Spacing</div>
        <div style="height:6px;background:var(--color-border);width:60%"></div>
      </div>
    </div>
  </div>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '900px']),

                // Section 3: Core Values
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— What you get</p>']),
                            $this->block('heading', ['text' => 'A modern editor. A timeless output.', 'level' => 'h2']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Edit visually', 'level' => 'h3', 'fontSize' => '1.4rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Build pages with structured blocks, reusable sections, and live previews. Your content is data, not a wall of markup.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Publish instantly', 'level' => 'h3', 'fontSize' => '1.4rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Generate fast static pages ready for your host, CDN, or deployment workflow. No server application required.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Keep everything', 'level' => 'h3', 'fontSize' => '1.4rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Your content, media, design tokens, and output remain portable. Export anytime. Host anywhere. Move whenever you want.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 4: Use Cases
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Use cases</p>']),
                            $this->block('heading', ['text' => 'Built for work that deserves its own shape.', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);max-width:52ch">From editorial publications to creative portfolios, Stillopress adapts to the kind of site you actually want to make.</p>']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Editorial publications', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Long-form articles, issue navigation, pull quotes, rich media. Built for readers.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Creative portfolios', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Gallery-forward layouts, case studies, large images. Built for visual work.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Design studios', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Client projects, team pages, refined typography. Built for craft.</p>']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Product pages', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Landing pages, feature sections, pricing tables. Built for conversion.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Documentation', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Sidebar navigation, code blocks, search-ready structure. Built for reference.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Personal websites', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Clean, fast, independent. Built for people who want to own their corner of the web.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 5: WordPress Migration
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Migration</p>']),
                            $this->block('heading', ['text' => 'Leave WordPress without leaving your work behind.', 'level' => 'h2', 'fontSize' => 'clamp(1.9rem,4.2vw,3.4rem)']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);max-width:52ch">Import your posts, categories, featured images, and Gutenberg content. Then rebuild your site in a cleaner, faster system.</p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('html-embed', ['html' => '<div style="display:flex;align-items:center;gap:clamp(.8rem,2vw,1.5rem);flex-wrap:wrap;font-family:var(--font-heading);font-size:clamp(.8rem,1.2vw,1rem)">
  <div style="padding:.8em 1.2em;border:1px solid var(--color-border);color:var(--color-text-muted)">WordPress content</div>
  <span style="color:var(--color-primary)">→</span>
  <div style="padding:.8em 1.2em;border:1px solid var(--color-border);color:var(--color-text-muted)">Import</div>
  <span style="color:var(--color-primary)">→</span>
  <div style="padding:.8em 1.2em;border:1px solid var(--color-border);color:var(--color-text-muted)">Structured blocks</div>
  <span style="color:var(--color-primary)">→</span>
  <div style="padding:.8em 1.2em;border:1px solid var(--color-primary);color:var(--color-primary);font-weight:600">Static site</div>
</div>']),
                            $this->block('spacer', ['height' => '32px']),
                            $this->block('button', ['text' => 'Explore migration', 'url' => '/docs', 'style' => 'outline']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '900px']),

                // Section 6: Technical Proof
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Architecture</p>']),
                            $this->block('heading', ['text' => 'Fast by architecture, not by optimization tricks.', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);max-width:52ch">Visitors receive finished pages, not an application that has to assemble itself on every request. Static output removes most of the performance overhead that makes modern websites slow.</p>']),
                        ]),
                    ]),
                    $this->row('1/3+1/3+1/3', [
                        $this->column([
                            $this->block('heading', ['text' => 'Static HTML output', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Every page publishes as clean HTML and CSS. No runtime framework. No database on the public side.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Deploy anywhere', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Your host, your CDN, your infrastructure. The output is ordinary files that work everywhere.</p>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'Roll back safely', 'level' => 'h3', 'fontSize' => '1.2rem']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Every publish is a snapshot. Restore any previous version with a single action.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 7: Trust
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Safer by design.', 'level' => 'h2', 'textAlign' => 'center']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);max-width:48ch;margin:0 auto;text-align:center">Your public site is static. No public database, no runtime application, and no plugin ecosystem exposed to visitors.</p>']),
                            $this->block('spacer', ['height' => '16px']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center"><a href="/docs" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.86rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.2em;text-decoration:none">Explore security architecture →</a></p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '900px']),

                // Section 8: Final CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Build something that stays yours.', 'level' => 'h2', 'textAlign' => 'center', 'color' => '#f4f2ec', 'fontSize' => 'clamp(2rem,5vw,3.4rem)']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center;color:#9d9a90;max-width:44ch;margin:0 auto 2rem">Create your site in Stillopress. Publish it anywhere. Keep it forever.</p>']),
                            $this->block('html-embed', ['html' => '<div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
  <a href="/pricing" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.9rem;padding:.9em 1.4em;background:var(--color-primary);color:#fff;border:1px solid var(--color-primary);text-decoration:none">Start building free →</a>
  <a href="/examples" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.9rem;padding:.9em 1.4em;background:transparent;color:#f4f2ec;border:1px solid #2c2b28;text-decoration:none">View examples</a>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '100px', 'max_width' => '800px', 'background_color' => '#121210']),
            ],
        ];
    }

    private function featuresPage(): array
    {
        return [
            'title' => 'Product',
            'slug' => 'features',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Product</p>']),
                            $this->block('heading', ['text' => 'Everything to build it. Nothing shipped to the reader.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">A complete visual authoring platform on one side, and flat static files on the other. Here is what fills the gap.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Feature matrix
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('catalog', ['openFirst' => false, 'imageFilter' => 'none', 'headerLabels' => ['', 'Feature', 'Category', ''], 'items' => [
                                ['title' => 'Visual block editor', 'subtitle' => 'Composition', 'content' => '<p>Build pages with structured blocks that nest and reorder freely. Each block renders identically in the editor and on the published site.</p><ul><li>93 block types across nine categories</li><li>Section, row, column, module hierarchy</li><li>Reusable block templates</li><li>Drag, drop, keyboard reorder</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Design-token theme engine', 'subtitle' => 'Theming', 'content' => '<p>Themes are W3C design tokens, resolved and compiled to CSS. Change a token, preview the whole site, publish.</p><ul><li>Theme Studio with live preview</li><li>Token references and inheritance</li><li>Per-site overrides</li><li>Coverage analysis built in</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Atomic static publish', 'subtitle' => 'Publishing', 'content' => '<p>A full build renders to static HTML, then swaps into place atomically. Every publish is a snapshot you can restore.</p><ul><li>Symlink swap or atomic rename</li><li>Rollback to any version</li><li>Content-hashed assets</li><li>No request-time rendering</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Built-in SEO', 'subtitle' => 'Discovery', 'content' => '<p>Meta tags, sitemaps, and structured data are generated on every publish — not bolted on as plugins.</p><ul><li>Sitemaps and robots.txt</li><li>Open Graph metadata</li><li>Clean URL structure</li><li>Semantic static markup</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'AI composition tools', 'subtitle' => 'Authoring', 'content' => '<p>Paste raw text and the AI composer turns it into a real page built from the block system — reviewable, not a black box.</p><ul><li>Text in, structured page out</li><li>Native blocks, fully editable</li><li>Per-tenant token budgets</li><li>Editorial judgement, not filler</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Block editor and magazine canvas', 'subtitle' => 'Layout', 'content' => '<p>A quiet vertical block editor for most pages, and a freeform magazine canvas when a spread needs to be composed by hand.</p><ul><li>Block editor — focused, linear</li><li>Magazine editor — freeform canvas</li><li>Shared block data model</li><li>Same publish pipeline for both</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Structured page hierarchy', 'subtitle' => 'Structure', 'content' => '<p>A real four-level hierarchy — section, row, column, module — so pages stay structured instead of becoming a soup of divs.</p><ul><li>Enforced page hierarchy</li><li>Wireframe and visual modes</li><li>Responsive by default</li><li>Consistent, predictable output</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Security architecture', 'subtitle' => 'Defence', 'content' => '<p>The published site is inert static files. The studio is protected from network to database.</p><ul><li>Isolated admin origin</li><li>RBAC and row-level security</li><li>HTML sanitisation on render</li><li>CSP, HSTS, verified uploads</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Import and export', 'subtitle' => 'Portability', 'content' => '<p>Arrive from WordPress without losing your archive. Leave with everything whenever you like.</p><ul><li>WordPress WXR import</li><li>Gutenberg block mapping</li><li>Category tree and media re-host</li><li>Full site export, any time</li></ul>', 'contentSecondary' => '', 'images' => []],
                            ]]),
                            $this->block('spacer', ['height' => '40px']),
                            $this->block('button', ['text' => 'Start building free →', 'url' => '/pricing', 'style' => 'primary', 'size' => 'lg']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function aboutPage(): array
    {
        return [
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— About</p>']),
                            $this->block('heading', ['text' => 'Stillness in the studio. Precision on the wire.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Manifesto
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'A website should be calm to make and quiet to serve.', 'level' => 'h2', 'fontSize' => 'clamp(1.6rem,3.6vw,2.4rem)', 'fontWeight' => '400']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 3: Story
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55">Stillopress began with a frustration: modern content platforms are loud. They ship a runtime, a database and a framework to every reader, then spend the rest of their lives trying to make that fast again.</p>']),
                        ]),
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">We took the opposite path. Edit against a rich, dynamic studio. Publish a flat, static site. The two never meet at request time — which is exactly why the reader\'s experience is instant, and why what you own is simply <em>files</em>.</p>']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">The name is deliberate. <em>Still</em> — the calm of a focused editing surface. <em>Press</em> — the old craft of putting something permanent onto the page. The ensō, drawn in one unbroken breath, is the mark of that intent.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 4: Beliefs
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— What we believe</p>']),
                            $this->block('heading', ['text' => 'What we build against.', 'level' => 'h2']),
                            $this->block('catalog', ['openFirst' => false, 'imageFilter' => 'none', 'headerLabels' => ['Principle', '', '', ''], 'items' => [
                                ['title' => 'Own your work', 'subtitle' => '', 'content' => '<p>Your website should never depend on a vendor\'s servers staying up, or a plan staying paid. Static output means the thing you publish is genuinely, portably yours.</p>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Speed is a floor', 'subtitle' => '', 'content' => '<p>Performance shouldn\'t be a feature you buy or a plugin you install. If there is nothing to render at request time, there is nothing to make slow.</p>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Structure over markup', 'subtitle' => '', 'content' => '<p>Content is data, not a blob of HTML. Typed blocks and an enforced hierarchy keep pages meaningful, portable, and safe to re-theme years later.</p>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Defence in depth', 'subtitle' => '', 'content' => '<p>The safest surface is the one that doesn\'t exist. A static site can\'t run an exploit; the studio behind it is guarded from the network down to the row.</p>', 'contentSecondary' => '', 'images' => []],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 5: Timeline
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— The build</p>']),
                            $this->block('heading', ['text' => 'Where the project stands.', 'level' => 'h2']),
                            $this->block('timeline', ['layout' => 'left', 'items' => [
                                ['date' => 'Core', 'title' => 'Block engine, static publish pipeline, atomic swap & rollback', 'description' => 'Shipped'],
                                ['date' => 'Themes', 'title' => 'Design-token engine, Theme Studio, live preview, coverage analysis', 'description' => 'Shipped'],
                                ['date' => 'Editors', 'title' => 'Block editor complete · freeform magazine canvas in progress', 'description' => 'In progress'],
                                ['date' => 'AI', 'title' => 'AI Page Composer — paste-to-structured-page', 'description' => 'In progress'],
                                ['date' => 'Import', 'title' => 'WordPress WXR import with Gutenberg mapping', 'description' => 'Planned'],
                                ['date' => 'Distribution', 'title' => 'One-click installer, shared-hosting build, auto-update', 'description' => 'Planned'],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function examplesPage(): array
    {
        return [
            'title' => 'Examples',
            'slug' => 'examples',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Examples</p>']),
                            $this->block('heading', ['text' => 'See what a static site can still do.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Real sites, all published from Stillopress. Built with the same blocks and themes available to every user.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Showcase
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--color-border);border-bottom:0"><style>@media(max-width:760px){.ex-grid{grid-template-columns:1fr !important}}</style><div class="ex-card" style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.4rem,2.4vw,2.2rem);display:flex;flex-direction:column;gap:.8rem"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 30%,var(--color-bg)));border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem"><div style="font-family:var(--font-heading);font-size:clamp(1.1rem,1.6vw,1.5rem);font-weight:600;color:var(--color-heading);margin-bottom:.4rem">The Quarterly</div><div style="width:40px;height:1px;background:var(--color-primary);margin-bottom:.4rem"></div><div style="font-size:.72rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Issue 01 · Spring</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.68rem;color:var(--color-primary);font-weight:600">Editorial</div><h3 style="font-family:var(--font-heading);font-size:1.35rem;font-weight:600;margin:0;line-height:1.1">The Quarterly</h3><p style="color:var(--color-text-muted);font-size:.92rem;margin:0;line-height:1.5">A journal-style site for essays, stories, and issues. Long-form editorial with rhythm and hierarchy.</p><a href="/examples" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.8rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><div class="ex-card" style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.4rem,2.4vw,2.2rem);display:flex;flex-direction:column;gap:.8rem"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,color-mix(in srgb,var(--color-bg-alt) 80%,var(--color-text)),var(--color-bg-alt));border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-end;padding:1.5rem"><div style="font-family:var(--font-heading);font-size:clamp(.9rem,1.3vw,1.2rem);font-weight:600;color:var(--color-heading)">Studio Forma</div><div style="font-size:.68rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Selected work · 2024</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.68rem;color:var(--color-primary);font-weight:600">Portfolio</div><h3 style="font-family:var(--font-heading);font-size:1.35rem;font-weight:600;margin:0;line-height:1.1">Studio Forma</h3><p style="color:var(--color-text-muted);font-size:.92rem;margin:0;line-height:1.5">A visual studio site with work, perspective, and case studies. Built for creative confidence.</p><a href="/examples" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.8rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><div class="ex-card" style="border-bottom:1px solid var(--color-border);padding:clamp(1.4rem,2.4vw,2.2rem);display:flex;flex-direction:column;gap:.8rem"><div style="aspect-ratio:16/10;background:var(--color-bg);border:1px solid var(--color-border);display:flex;align-items:flex-start;padding:1rem;gap:.8rem"><div style="width:30%;border-right:1px solid var(--color-border);padding-right:.6rem"><div style="font-size:.55rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted);margin-bottom:.4rem">Docs</div><div style="height:4px;background:var(--color-border);width:80%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-border);width:60%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-primary);width:70%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-border);width:55%"></div></div><div style="flex:1"><div style="font-family:var(--font-heading);font-size:.8rem;font-weight:600;margin-bottom:.3rem">Getting started</div><div style="height:3px;background:var(--color-border);width:90%;margin-bottom:.15rem"></div><div style="height:3px;background:var(--color-border);width:75%;margin-bottom:.15rem"></div><div style="height:3px;background:var(--color-border);width:60%"></div></div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.68rem;color:var(--color-primary);font-weight:600">Documentation</div><h3 style="font-family:var(--font-heading);font-size:1.35rem;font-weight:600;margin:0;line-height:1.1">Atlas Docs</h3><p style="color:var(--color-text-muted);font-size:.92rem;margin:0;line-height:1.5">A structured product documentation and changelog experience. Fast, searchable, static.</p><a href="/examples" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.8rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div></div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: Block categories
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Block library</p>']),
                            $this->block('heading', ['text' => '93 blocks, grouped nine ways.', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Every example above is built from the same set. Nothing bespoke, nothing off-menu.</p>']),
                            $this->block('stats', ['columns' => 3, 'items' => [
                                ['value' => '12', 'label' => 'Structure'],
                                ['value' => '14', 'label' => 'Text & editorial'],
                                ['value' => '11', 'label' => 'Media'],
                                ['value' => '10', 'label' => 'Layout & grid'],
                                ['value' => '8', 'label' => 'Navigation'],
                                ['value' => '9', 'label' => 'Commerce & forms'],
                                ['value' => '8', 'label' => 'Embeds'],
                                ['value' => '11', 'label' => 'Interactive'],
                                ['value' => '10', 'label' => 'Cinematic / motion'],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function pricingPage(): array
    {
        return [
            'title' => 'Pricing',
            'slug' => 'pricing',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Pricing</p>']),
                            $this->block('heading', ['text' => 'Free is a full CMS. Not a trial.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Every plan publishes real static sites you own. Paid tiers add power tools and scale — never the right to keep your files.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Pricing table
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('pricingtable', ['columns' => 3, 'plans' => [
                                ['name' => 'Free', 'price' => '€0', 'period' => 'forever', 'features' => ['One site', 'Core visual editor', 'Static export', 'Core block library', 'Self-hosted publishing', 'No credit card required'], 'ctaText' => 'Start free', 'ctaUrl' => '/contact', 'highlighted' => false],
                                ['name' => 'Maker', 'price' => '€15', 'period' => '/mo', 'features' => ['Multiple sites', 'Custom domains', 'WordPress import', 'AI composition tools', 'Premium themes', 'Advanced publishing', 'Priority support'], 'ctaText' => 'Choose Maker', 'ctaUrl' => '/contact', 'highlighted' => true],
                                ['name' => 'Studio', 'price' => '€59', 'period' => '/mo', 'features' => ['Client workspaces', 'Team roles and permissions', 'White-label export', 'Advanced collaboration', 'Priority onboarding', 'Dedicated support'], 'ctaText' => 'Choose Studio', 'ctaUrl' => '/contact', 'highlighted' => false],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: FAQ
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Questions</p>']),
                            $this->block('heading', ['text' => 'Common questions.', 'level' => 'h2']),
                            $this->block('accordion', ['openFirst' => false, 'items' => [
                                ['title' => 'Do I really own the published site?', 'content' => '<p>Yes — completely. Publishing renders your pages to static HTML, CSS and images on your own host. If you cancelled tomorrow, every file would keep working exactly as it is. You can also export the whole site at any time.</p>'],
                                ['title' => 'Where are sites hosted?', 'content' => '<p>You choose. Stillopress publishes static files — deploy them to your own server, a CDN, shared hosting, or any infrastructure you control. Paid plans can also manage hosting for you.</p>'],
                                ['title' => 'What happens if I cancel?', 'content' => '<p>Your exported site remains yours. You can host it anywhere, keep it locally, or deploy it through your own infrastructure. The published files have no dependency on Stillopress.</p>'],
                                ['title' => 'Can I move an existing WordPress site?', 'content' => '<p>On the Maker plan, point the importer at a WordPress WXR export. It maps Gutenberg blocks to native blocks, rebuilds your category hierarchy, re-hosts your media, and preserves featured images.</p>'],
                                ['title' => 'Does the free plan require a credit card?', 'content' => '<p>No. The free plan is self-hosted. You bring your own hosting — a small VPS or even shared hosting is enough, since the output is just static files.</p>'],
                                ['title' => 'Can I self-host the entire CMS?', 'content' => '<p>Yes. Stillopress is designed to run on your own server. Laravel, PostgreSQL, and Node.js. Full installation guide in the docs.</p>'],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '1100px']),
            ],
        ];
    }

    private function docsPage(): array
    {
        return [
            'title' => 'Documentation',
            'slug' => 'docs',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Documentation</p>']),
                            $this->block('heading', ['text' => 'Start here.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">From first publish to the block API. Everything you need to run Stillopress well.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Doc content
                $this->section([
                    $this->row('1/3+2/3', [
                        // Column 1: Sidebar
                        $this->column([
                            $this->block('heading', ['text' => 'Getting started', 'level' => 'h4', 'fontSize' => '0.74rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">Install &amp; setup</a><br><a href="/docs">Your first site</a><br><a href="/docs">Publishing basics</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Block editor', 'level' => 'h4', 'fontSize' => '0.74rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">The block model</a><br><a href="/docs">Nesting &amp; templates</a><br><a href="/docs">Magazine canvas</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Themes', 'level' => 'h4', 'fontSize' => '0.74rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">Design tokens</a><br><a href="/docs">Theme Studio</a><br><a href="/docs">Overrides</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Publishing', 'level' => 'h4', 'fontSize' => '0.74rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">Atomic swap</a><br><a href="/docs">Rollback</a><br><a href="/docs">Custom domains</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'API reference', 'level' => 'h4', 'fontSize' => '0.74rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">Blocks API</a><br><a href="/docs">Deploy API</a><br><a href="/docs">Webhooks</a></p>']),
                        ]),
                        // Column 2: Body
                        $this->column([
                            $this->block('heading', ['text' => 'Getting started', 'level' => 'h2', 'fontSize' => 'clamp(1.9rem,4.2vw,3.4rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;margin-bottom:2rem">Publish your first static page in under ten minutes. This guide walks the whole loop — compose, preview, publish.</p>']),
                            $this->block('heading', ['text' => 'Install & setup →', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Requirements, environment, and getting the studio running on your host.</p>']),
                            $this->block('divider', []),
                            $this->block('heading', ['text' => 'Your first site →', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Create a site, add a page, and stack your first blocks in the editor.</p>']),
                            $this->block('divider', []),
                            $this->block('heading', ['text' => 'Publishing basics →', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">How a build renders to static files and swaps into place atomically.</p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('code', ['language' => 'bash', 'code' => "# publish the current site to static HTML\nstillopress publish --site my-site --atomic\n\n# roll back to the previous snapshot\nstillopress rollback --site my-site --to latest-1"]),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);margin-top:1.5rem">Prefer to read end to end? The full documentation covers themes, the block API and deployment in depth.</p>']),
                            $this->block('button', ['text' => 'Ask a question', 'url' => '/contact', 'style' => 'outline']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function contactPage(): array
    {
        return [
            'title' => 'Contact',
            'slug' => 'contact',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Contact</p>']),
                            $this->block('heading', ['text' => 'Say hello.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Questions about the product, a plan, or moving your site over. We read everything.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Form + aside
                $this->section([
                    $this->row('1/2+1/2', [
                        // Column 1: Form
                        $this->column([
                            $this->block('contact-form', [
                                'recipient_email' => 'hello@stillopress.com',
                                'submit_label' => 'Send message →',
                                'success_message' => 'Thank you. We\'ll get back to you within 24 hours.',
                                'fields' => [
                                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                                    ['label' => 'Email', 'type' => 'email', 'required' => true],
                                    ['label' => 'Topic', 'type' => 'select', 'required' => false],
                                    ['label' => 'Message', 'type' => 'textarea', 'required' => true],
                                ],
                            ]),
                        ]),
                        // Column 2: Aside
                        $this->column([
                            $this->block('heading', ['text' => 'Email', 'level' => 'h4', 'fontSize' => '0.76rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="mailto:hello@stillopress.com">hello@stillopress.com</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Docs', 'level' => 'h4', 'fontSize' => '0.76rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p><a href="/docs">Read the documentation →</a></p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Made by', 'level' => 'h4', 'fontSize' => '0.76rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p>Cytechno — a studio building tools it wanted to use.</p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('heading', ['text' => 'Response time', 'level' => 'h4', 'fontSize' => '0.76rem', 'textTransform' => 'uppercase', 'letterSpacing' => '0.14em']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Usually within a day, in calm and complete sentences.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '1100px']),
            ],
        ];
    }

    // ─── Block helpers ──────────────────────────────────────────────────

    private function section(array $children, array $data = []): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => array_merge(['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'], $data),
            'children' => $children,
        ];
    }

    private function row(string $layout, array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'row',
            'level' => 'row',
            'order' => $this->rowOrder++,
            'data' => ['layout' => $layout, 'gap' => '24px'],
            'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'column',
            'level' => 'column',
            'order' => $this->colOrder++,
            'data' => [],
            'children' => $children,
        ];
    }

    private function block(string $type, array $data): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => $this->blockOrder++,
            'data' => $data,
            'children' => [],
        ];
    }

    private function resetCounters(): void
    {
        $this->rowOrder = 0;
        $this->colOrder = 0;
        $this->blockOrder = 0;
    }
}
