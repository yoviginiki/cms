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
            $this->theQuarterlyPage(),
            $this->studioFormaPage(),
            $this->atlasDocsPage(),
            $this->noirArchitectsPage(),
            $this->groundCoffeePage(),
            $this->solsticeRecordsPage(),
            $this->templatesPage(),
            $this->wordpressMigrationPage(),
            $this->securityPage(),
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
                            $this->block('html-embed', ['html' => '<div class="ex-grid-6" style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--color-border);border-bottom:0"><style>@media(max-width:1000px){.ex-grid-6{grid-template-columns:repeat(2,1fr) !important}}@media(max-width:600px){.ex-grid-6{grid-template-columns:1fr !important}}</style><!-- Card 1: The Quarterly --><div style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 30%,var(--color-bg)));border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.2rem"><div style="font-family:var(--font-heading);font-size:clamp(1.1rem,1.6vw,1.5rem);font-weight:600;color:var(--color-heading);margin-bottom:.4rem">The Quarterly</div><div style="width:40px;height:1px;background:var(--color-primary);margin-bottom:.4rem"></div><div style="font-size:.72rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Issue 01 · Spring</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">Editorial</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">The Quarterly</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A journal-style site for essays, stories, and issues. Long-form editorial with rhythm and hierarchy.</p><a href="/the-quarterly" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><!-- Card 2: Studio Forma --><div style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,color-mix(in srgb,var(--color-bg-alt) 80%,var(--color-text)),var(--color-bg-alt));border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-end;padding:1.2rem"><div style="font-family:var(--font-heading);font-size:clamp(.9rem,1.3vw,1.2rem);font-weight:600;color:var(--color-heading)">Studio Forma</div><div style="font-size:.68rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Selected work · 2024</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">Portfolio</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">Studio Forma</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A visual studio site with work, perspective, and case studies. Built for creative confidence.</p><a href="/studio-forma" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><!-- Card 3: Atlas Docs --><div style="border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:var(--color-bg);border:1px solid var(--color-border);display:flex;align-items:flex-start;padding:1rem;gap:.8rem"><div style="width:30%;border-right:1px solid var(--color-border);padding-right:.6rem"><div style="font-size:.55rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted);margin-bottom:.4rem">Docs</div><div style="height:4px;background:var(--color-border);width:80%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-border);width:60%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-primary);width:70%;margin-bottom:.25rem"></div><div style="height:4px;background:var(--color-border);width:55%"></div></div><div style="flex:1"><div style="font-family:var(--font-heading);font-size:.8rem;font-weight:600;margin-bottom:.3rem">Getting started</div><div style="height:3px;background:var(--color-border);width:90%;margin-bottom:.15rem"></div><div style="height:3px;background:var(--color-border);width:75%;margin-bottom:.15rem"></div><div style="height:3px;background:var(--color-border);width:60%"></div></div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">Documentation</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">Atlas Docs</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A structured product documentation and changelog experience. Fast, searchable, static.</p><a href="/atlas-docs" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><!-- Card 4: Noir Architects --><div style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:linear-gradient(160deg,#121210,#1e1e1a,#2a2a24);border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.2rem"><div style="font-family:var(--font-heading);font-size:clamp(1.2rem,1.8vw,1.6rem);font-weight:600;color:#f4f2ec;letter-spacing:.15em">NOIR</div><div style="width:30px;height:1px;background:var(--color-primary);margin:.3rem 0"></div><div style="font-size:.6rem;color:#888;text-transform:uppercase;letter-spacing:.12em">Architecture</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">Architecture</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">Noir Architects</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A dark, dramatic portfolio for an international architecture firm.</p><a href="/noir-architects" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><!-- Card 5: Ground Coffee --><div style="border-right:1px solid var(--color-border);border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:linear-gradient(145deg,#3e2b1a,#5c3d24,#8b6f47);border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.2rem"><div style="font-family:var(--font-heading);font-size:clamp(1.1rem,1.6vw,1.4rem);font-weight:600;color:#f4efe6;letter-spacing:.12em">GROUND</div><div style="font-size:.58rem;color:#c4a876;text-transform:uppercase;letter-spacing:.1em;margin-top:.3rem">Specialty Coffee</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">E-commerce</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">Ground Coffee</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A warm artisanal coffee brand with product showcase and origin stories.</p><a href="/ground-coffee" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div><!-- Card 6: Solstice Records --><div style="border-bottom:1px solid var(--color-border);padding:clamp(1.2rem,2vw,1.8rem);display:flex;flex-direction:column;gap:.7rem"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,#e8e6e1,#d4d2cd,#c0beb9);border:1px solid var(--color-border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.2rem"><div style="font-family:var(--font-heading);font-size:clamp(1rem,1.5vw,1.3rem);font-weight:600;color:#1a1a1a;letter-spacing:.18em">SOLSTICE</div><div style="font-size:.58rem;color:#c0392b;text-transform:uppercase;letter-spacing:.1em;margin-top:.3rem">Independent Label</div></div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-primary);font-weight:600">Music</div><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0;line-height:1.1">Solstice Records</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.45">A minimal typographic site for an independent record label.</p><a href="/solstice-records" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.09em;font-size:.76rem;color:var(--color-text);border-bottom:1px solid var(--color-text);padding-bottom:.15em;text-decoration:none;align-self:flex-start">View example →</a></div></div>']),
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
                            $this->block('heading', ['text' => 'The CMS is free. Pay only for the services you need.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Stillopress is a free, open-source CMS. The visual editor, all 93 blocks, static publishing, themes, and export are included at no cost. We charge for optional services — managed hosting, AI tools, API access, and dedicated infrastructure.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Free core
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);padding:clamp(2rem,4vw,3rem)">
<div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
  <div>
    <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.16em;font-size:.72rem;color:var(--color-primary);font-weight:600;margin:0 0 .3rem">Open source</p>
    <h2 style="font-family:var(--font-heading);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:600;margin:0;line-height:1">Stillopress Core</h2>
  </div>
  <div style="font-family:var(--font-heading);font-size:clamp(2rem,4vw,3rem);font-weight:600;letter-spacing:-.02em">Free</div>
</div>
<p style="color:var(--color-text-muted);max-width:52ch;margin:0 0 2rem;line-height:1.55">The complete CMS — no feature gates, no trial limits, no surprises. Self-host on your own server and build as many sites as you want.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0;border-top:1px solid var(--color-border)">
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">All 93 block types</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Visual block editor</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Design-token themes</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Static HTML publishing</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Full site export</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Unlimited sites</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Magazine / DTP editor</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">WordPress import</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">SEO &amp; sitemaps</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Rollback &amp; versioning</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Community support</div>
  <div style="padding:1rem 1rem 1rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">No credit card required</div>
</div>
<div style="margin-top:2rem"><a href="/contact" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.9rem;padding:.9em 1.4em;background:var(--color-text);color:var(--color-bg);border:1px solid var(--color-text);text-decoration:none">Download Stillopress →</a></div>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 3: Paid services
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Optional services</p>']),
                            $this->block('heading', ['text' => 'Add what you need. Skip what you don\'t.', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted);max-width:52ch">Every service below is optional. The core CMS works completely without them.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '20px', 'max_width' => '1100px']),

                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('pricingtable', ['columns' => 3, 'plans' => [
                                ['name' => 'Managed Hosting', 'price' => '€9', 'period' => '/site/mo', 'features' => ['For creators who want zero server management', '—', 'Automatic static deployment', 'Custom domain + SSL', 'Global CDN', 'Daily backups', '99.9% uptime SLA', 'One-click publish from CMS'], 'ctaText' => 'Add hosting', 'ctaUrl' => '/contact', 'highlighted' => false],
                                ['name' => 'AI Tools', 'price' => '€12', 'period' => '/mo', 'features' => ['For teams that want intelligent content assistance', '—', 'AI Page Composer', 'Content rewriting & translation', 'SEO meta generation', 'Image alt text (vision)', 'Per-tenant usage budgets', 'Bring your own API key option'], 'ctaText' => 'Add AI tools', 'ctaUrl' => '/contact', 'highlighted' => true],
                                ['name' => 'Pro Infrastructure', 'price' => '€29', 'period' => '/mo', 'features' => ['For agencies and teams that need scale', '—', 'REST API access', 'Dedicated PostgreSQL database', 'Team roles & RBAC', 'Multi-tenant isolation (RLS)', 'Webhook integrations', 'Priority support & SLA'], 'ctaText' => 'Add infrastructure', 'ctaUrl' => '/contact', 'highlighted' => false],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '40px', 'max_width' => '1320px']),

                // Section 4: Comparison
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:.9rem;min-width:600px">
<thead>
<tr style="border-bottom:2px solid var(--color-text)">
  <th style="text-align:left;padding:1rem;font-family:var(--font-heading);font-weight:600">Feature</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600">Free (self-hosted)</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600;color:var(--color-primary)">+ Hosting</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600">+ AI</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600">+ Pro Infra</th>
</tr>
</thead>
<tbody>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Visual block editor</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">All 93 blocks</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Static export &amp; publish</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Themes &amp; design tokens</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">WordPress import</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Unlimited sites</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Managed hosting &amp; CDN</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Custom domain + SSL</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">AI Page Composer</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Content translation</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">REST API access</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Dedicated database</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
<tr><td style="padding:.8rem 1rem">Team roles &amp; RBAC</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">—</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">✓</td></tr>
</tbody>
</table>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '20px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 5: FAQ
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Questions</p>']),
                            $this->block('heading', ['text' => 'Common questions.', 'level' => 'h2']),
                            $this->block('accordion', ['openFirst' => false, 'items' => [
                                ['title' => 'Is the CMS really free?', 'content' => '<p>Yes. Stillopress Core is free and open source. The visual editor, all 93 blocks, themes, static publishing, WordPress import, and full site export are included. You self-host it on your own server — there are no feature gates or trial limits.</p>'],
                                ['title' => 'What do the paid services add?', 'content' => '<p>Managed hosting removes server management — we deploy your static site to a global CDN with custom domains and SSL. AI tools add intelligent content composition, translation, and SEO generation. Pro infrastructure adds API access, a dedicated database, team roles, and webhook integrations. Each service is independent — add only what you need.</p>'],
                                ['title' => 'Do I keep my website if I cancel a service?', 'content' => '<p>Yes. Your exported site remains yours. The published static files have no dependency on any paid service. Cancel hosting and your files still work on any server. Cancel AI and your existing content stays. The CMS itself remains free.</p>'],
                                ['title' => 'Can I use my own AI API key?', 'content' => '<p>Yes. If you prefer to use your own Anthropic or OpenAI key, you can configure it in the CMS settings and skip the AI service tier entirely.</p>'],
                                ['title' => 'Where is the CMS hosted?', 'content' => '<p>You host it. Stillopress is a self-hosted application built on Laravel, PostgreSQL, and Node.js. Install it on your own VPS, dedicated server, or cloud instance. Full installation guide in the docs.</p>'],
                                ['title' => 'Is there a managed CMS option?', 'content' => '<p>A fully managed Stillopress Cloud — where we host both the CMS and your published sites — is planned. For now, the CMS is self-hosted and managed hosting covers only the static site output.</p>'],
                                ['title' => 'Does the free plan require a credit card?', 'content' => '<p>No. Download and install. No account, no credit card, no sign-up required for the core CMS.</p>'],
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

    // ─── Example detail pages ────────────────────────────────────────────

    private function theQuarterlyPage(): array
    {
        return [
            'title' => 'The Quarterly',
            'slug' => 'the-quarterly',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1.4rem 0;border-bottom:1px solid var(--color-border)">
  <span style="font-family:var(--font-heading);font-weight:600;font-size:1.3rem;letter-spacing:.02em">The Quarterly</span>
  <div style="display:flex;gap:clamp(1rem,2vw,2rem);font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-text-muted)"><span>Essays</span><span>Interviews</span><span>Archive</span><span style="color:var(--color-primary)">Issue 01</span></div>
</nav>
<div style="padding:clamp(3rem,8vh,6rem) 0;text-align:center">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.22em;font-size:.72rem;color:var(--color-primary);font-weight:600;margin:0 0 1.2rem">Issue 01 · Spring</p>
  <h1 style="font-family:var(--font-heading);font-size:clamp(3rem,10vw,7rem);font-weight:600;line-height:.88;letter-spacing:-.02em;margin:0 0 1.6rem">The Quarterly</h1>
  <p style="font-size:clamp(1rem,1.3vw,1.2rem);color:var(--color-text-muted);max-width:38ch;margin:0 auto 2rem;line-height:1.5">A journal of ideas, work, and perspective. Published from the studio.</p>
  <div style="width:60px;height:1px;background:var(--color-primary);margin:0 auto"></div>
</div>
<div style="max-width:680px;margin:0 auto;padding-bottom:clamp(3rem,6vh,5rem)">
  <div style="aspect-ratio:16/9;background:linear-gradient(145deg,color-mix(in srgb,var(--color-bg-alt) 70%,var(--color-border)),var(--color-bg-alt));border:1px solid var(--color-border);margin-bottom:2.5rem;display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.15em;color:var(--color-text-muted)">Cover photograph</span></div>
  <h2 style="font-family:var(--font-heading);font-size:clamp(1.8rem,4vw,2.8rem);font-weight:600;line-height:1;margin:0 0 1rem">On the patience of making things that last</h2>
  <p style="color:var(--color-text-muted);line-height:1.65;margin:0 0 2rem">There is a particular stillness that comes after a thing is finished. Not the silence of emptiness, but the quiet confidence of something that holds together on its own — that doesn\'t need to explain itself.</p>
  <blockquote style="border-left:2px solid var(--color-primary);padding-left:1.5rem;margin:2.5rem 0;font-family:var(--font-heading);font-size:clamp(1.2rem,2.4vw,1.7rem);font-weight:400;line-height:1.25;color:var(--color-heading)">"The best work disappears into its own usefulness."</blockquote>
  <p style="color:var(--color-text-muted);line-height:1.65;margin:0 0 3rem">We started this journal because some ideas need more room than a changelog entry. Each issue collects essays, interviews, and observations from people who build carefully.</p>
  <div style="border-top:1px solid var(--color-border);padding-top:2rem">
    <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.7rem;color:var(--color-text-muted);margin:0 0 1.2rem">More in this issue</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem">
      <div style="border:1px solid var(--color-border);padding:1.2rem"><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .4rem;line-height:1.1">Tools and their shadows</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">On what software asks of the people who use it.</p></div>
      <div style="border:1px solid var(--color-border);padding:1.2rem"><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .4rem;line-height:1.1">A conversation with the studio</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">How a small team thinks about durability.</p></div>
      <div style="border:1px solid var(--color-border);padding:1.2rem"><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .4rem;line-height:1.1">The file that outlives the app</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">Why static output matters more than you think.</p></div>
    </div>
  </div>
</div>
<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">The Quarterly · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '900px']),
            ],
        ];
    }

    private function studioFormaPage(): array
    {
        return [
            'title' => 'Studio Forma',
            'slug' => 'studio-forma',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1.4rem 0;border-bottom:1px solid var(--color-border)">
  <span style="font-family:var(--font-heading);font-weight:600;font-size:1.3rem;letter-spacing:.02em">Studio Forma</span>
  <div style="display:flex;gap:clamp(1rem,2vw,2rem);font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-text-muted)"><span style="color:var(--color-primary)">Work</span><span>Studio</span><span>Contact</span></div>
</nav>
<div style="padding:clamp(4rem,10vh,8rem) 0 clamp(2rem,4vh,3rem)">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.22em;font-size:.72rem;color:var(--color-text-muted);font-weight:500;margin:0 0 1rem">Selected work · 2024</p>
  <h1 style="font-family:var(--font-heading);font-size:clamp(3rem,10vw,7rem);font-weight:600;line-height:.88;letter-spacing:-.02em;margin:0 0 1.6rem">Studio Forma</h1>
  <p style="font-size:clamp(1rem,1.3vw,1.2rem);color:var(--color-text-muted);max-width:40ch;line-height:1.5;margin:0">Identity, editorial, and digital work for brands that value restraint and lasting quality.</p>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1px;background:var(--color-border);margin-bottom:clamp(3rem,6vh,5rem)">
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:4/3;background:linear-gradient(135deg,color-mix(in srgb,var(--color-bg-alt) 60%,var(--color-border)),var(--color-bg-alt));display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-end;padding:1.5rem"><span style="font-family:var(--font-heading);font-weight:600;font-size:1.2rem">Meridian</span><span style="font-size:.72rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Brand identity</span></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:4/3;background:linear-gradient(180deg,var(--color-bg-alt),color-mix(in srgb,var(--color-text) 8%,var(--color-bg-alt)));display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-end;padding:1.5rem"><span style="font-family:var(--font-heading);font-weight:600;font-size:1.2rem">Archivist</span><span style="font-size:.72rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Editorial design</span></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:4/3;background:linear-gradient(225deg,var(--color-bg-alt),color-mix(in srgb,var(--color-primary) 5%,var(--color-bg-alt)));display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-end;padding:1.5rem"><span style="font-family:var(--font-heading);font-weight:600;font-size:1.2rem">Tonal</span><span style="font-size:.72rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Digital product</span></div></div>
</div>
<div style="max-width:600px;padding-bottom:clamp(3rem,6vh,5rem)">
  <h2 style="font-family:var(--font-heading);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:600;line-height:1;margin:0 0 1rem">About the studio</h2>
  <p style="color:var(--color-text-muted);line-height:1.65;margin:0 0 1rem">Forma is a small design studio that works with a handful of clients each year. We believe in restraint, precision, and the kind of craft that gets quieter as it gets better.</p>
  <p style="color:var(--color-text-muted);line-height:1.65;margin:0">Every project here is a case study in how much you can communicate by removing what doesn\'t belong.</p>
</div>
<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">Studio Forma · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '1320px']),
            ],
        ];
    }

    private function atlasDocsPage(): array
    {
        return [
            'title' => 'Atlas Docs',
            'slug' => 'atlas-docs',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1rem 0;border-bottom:1px solid var(--color-border)">
  <div style="display:flex;align-items:center;gap:.8rem"><div style="width:8px;height:8px;background:var(--color-primary);border-radius:50%"></div><span style="font-family:var(--font-heading);font-weight:600;font-size:1.1rem">Atlas</span><span style="font-family:var(--font-heading);font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted);border-left:1px solid var(--color-border);padding-left:.8rem">Docs</span></div>
  <div style="border:1px solid var(--color-border);padding:.4rem 1rem;font-size:.8rem;color:var(--color-text-muted);min-width:180px">Search docs…</div>
</nav>
<div style="display:grid;grid-template-columns:220px 1fr;gap:0;flex:1;min-height:70vh" class="docs-ex-grid">
  <aside style="border-right:1px solid var(--color-border);padding:2rem 1.5rem 2rem 0">
    <div style="margin-bottom:1.8rem"><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-text-muted);margin-bottom:.6rem">Getting started</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-primary);font-weight:500;border-left:2px solid var(--color-primary);padding-left:.8rem;margin-left:-.8rem">Introduction</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Installation</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Quick start</div></div>
    <div style="margin-bottom:1.8rem"><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-text-muted);margin-bottom:.6rem">Guides</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Authentication</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Data models</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Deployment</div></div>
    <div><div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-text-muted);margin-bottom:.6rem">API reference</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">REST endpoints</div><div style="padding:.3rem 0;font-size:.85rem;color:var(--color-text-muted);padding-left:.8rem">Webhooks</div></div>
  </aside>
  <main style="padding:2rem 0 2rem 2.5rem">
    <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.65rem;color:var(--color-text-muted);margin:0 0 .6rem">Getting started</p>
    <h1 style="font-family:var(--font-heading);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:600;line-height:1;margin:0 0 1rem">Introduction</h1>
    <p style="color:var(--color-text-muted);line-height:1.65;max-width:56ch;margin:0 0 2rem">Atlas is a modern API platform for structured content. This guide walks you through setup, your first schema, and your first deploy.</p>
    <div style="background:var(--color-bg-inverse);color:var(--color-bg);padding:1.2rem 1.5rem;font-family:var(--font-heading);font-size:.88rem;letter-spacing:.02em;margin:0 0 2rem;overflow-x:auto;max-width:100%"><span style="color:var(--color-primary)">atlas</span> init my-project<br><span style="color:var(--color-primary)">atlas</span> deploy --env production</div>
    <h2 style="font-family:var(--font-heading);font-size:1.4rem;font-weight:600;margin:0 0 .8rem">Prerequisites</h2>
    <p style="color:var(--color-text-muted);line-height:1.65;max-width:56ch;margin:0 0 1.5rem">You will need Node.js 20 or later and an Atlas account. The CLI handles everything else.</p>
    <h2 style="font-family:var(--font-heading);font-size:1.4rem;font-weight:600;margin:0 0 .8rem">Next steps</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;max-width:480px"><div style="border:1px solid var(--color-border);padding:1rem"><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .3rem;display:flex;justify-content:space-between">Installation <span style="color:var(--color-primary)">→</span></h3><p style="font-size:.8rem;color:var(--color-text-muted);margin:0">Set up Atlas locally.</p></div><div style="border:1px solid var(--color-border);padding:1rem"><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .3rem;display:flex;justify-content:space-between">Quick start <span style="color:var(--color-primary)">→</span></h3><p style="font-size:.8rem;color:var(--color-text-muted);margin:0">Build your first schema.</p></div></div>
    <div style="margin-top:3rem;border-top:1px solid var(--color-border);padding-top:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.65rem;color:var(--color-text-muted);margin:0 0 .8rem">Changelog</p><div style="margin-bottom:.8rem"><span style="font-family:var(--font-heading);font-weight:600;font-size:.88rem">v2.4.0</span> <span style="font-size:.78rem;color:var(--color-text-muted)">· June 2026</span><p style="font-size:.82rem;color:var(--color-text-muted);margin:.2rem 0 0">New webhook system, improved query performance, dark mode for docs.</p></div><div><span style="font-family:var(--font-heading);font-weight:600;font-size:.88rem">v2.3.1</span> <span style="font-size:.78rem;color:var(--color-text-muted)">· May 2026</span><p style="font-size:.82rem;color:var(--color-text-muted);margin:.2rem 0 0">Bug fixes, schema validation improvements.</p></div></div>
  </main>
</div>
<style>@media(max-width:760px){.docs-ex-grid{grid-template-columns:1fr !important}.docs-ex-grid aside{border-right:0;border-bottom:1px solid var(--color-border);padding:1rem 0}.docs-ex-grid main{padding:1.5rem 0 !important}}</style>
<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">Atlas Docs · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '1320px']),
            ],
        ];
    }

    private function noirArchitectsPage(): array
    {
        return [
            'title' => 'Noir Architects',
            'slug' => 'noir-architects',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1.4rem 0;border-bottom:1px solid rgba(244,242,236,.12)">
  <span style="font-family:var(--font-heading);font-weight:600;font-size:1.3rem;letter-spacing:.15em;color:#f4f2ec">NOIR</span>
  <div style="display:flex;gap:clamp(1rem,2vw,2rem);font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(244,242,236,.5)"><span style="color:var(--color-primary)">Projects</span><span>Studio</span><span>Team</span><span>Contact</span></div>
</nav>
<div style="padding:clamp(5rem,14vh,10rem) 0 clamp(2rem,4vh,3rem);text-align:center">
  <h1 style="font-family:var(--font-heading);font-size:clamp(4rem,14vw,10rem);font-weight:600;line-height:.82;letter-spacing:.08em;margin:0 0 1.6rem;color:#f4f2ec">NOIR</h1>
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.22em;font-size:.82rem;color:rgba(244,242,236,.5);margin:0 0 .8rem">Architecture &amp; Interior Design</p>
  <p style="font-size:.78rem;color:rgba(244,242,236,.35);letter-spacing:.15em;text-transform:uppercase;margin:0">Zurich · Tokyo · São Paulo</p>
</div>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '1320px', 'background_color' => '#121210']),

                // Section 2: Projects grid
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 2rem">— Selected projects</p>
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--color-border)" class="noir-proj-grid">
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:linear-gradient(160deg,#1a1a18,#2a2a24,#1e1e1a);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#666">Project image</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Cultural centre · Zurich · 2024</p><h3 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">The Glass Pavilion</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">A meditation on transparency and weight. 12,000m² of public space dissolved into landscape.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:linear-gradient(200deg,#222220,#1a1a18,#2e2e28);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#666">Project image</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Residential conversion · Tokyo · 2023</p><h3 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Warehouse 9</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">Former industrial storage reimagined as 24 live-work units. Concrete and light.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,#1e1e1a,#282824,#1a1a18);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#666">Project image</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Private residence · São Paulo · 2023</p><h3 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Casa Quieta</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">A house that turns its back to the street and opens entirely to the garden.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:linear-gradient(180deg,#242422,#1c1c1a,#2a2a26);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#666">Project image</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Office campus · Berlin · 2024</p><h3 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">The Fold</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">Two buildings connected by a continuous folded roof. 40,000m² of flexible workspace.</p></div></div>
</div>
<style>@media(max-width:700px){.noir-proj-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: Philosophy
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="max-width:680px;margin:0 auto;text-align:center;padding:clamp(3rem,8vh,6rem) 0">
  <div style="width:40px;height:1px;background:var(--color-primary);margin:0 auto 2rem"></div>
  <h2 style="font-family:var(--font-heading);font-size:clamp(1.6rem,3.5vw,2.4rem);font-weight:600;line-height:1.05;margin:0 0 1.5rem;color:#f4f2ec">We design buildings that outlast their architects.</h2>
  <p style="font-size:clamp(1rem,1.2vw,1.15rem);line-height:1.65;color:rgba(244,242,236,.55);margin:0">Every project begins with the question: what does this place need to become? Not what it should look like — but what it should feel like to stand inside, years from now.</p>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '1320px', 'background_color' => '#121210']),

                // Section 4: Team
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 2rem">— Partners</p>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--color-border)" class="noir-team-grid">
  <div style="background:var(--color-bg);padding:1.5rem"><div style="aspect-ratio:1/1;background:linear-gradient(145deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 20%,var(--color-bg)));border:1px solid var(--color-border);margin-bottom:1.2rem;display:flex;align-items:center;justify-content:center"><span style="font-size:.6rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Portrait</span></div><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Elena Noir</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">Founding Partner</p></div>
  <div style="background:var(--color-bg);padding:1.5rem"><div style="aspect-ratio:1/1;background:linear-gradient(145deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 20%,var(--color-bg)));border:1px solid var(--color-border);margin-bottom:1.2rem;display:flex;align-items:center;justify-content:center"><span style="font-size:.6rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Portrait</span></div><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Kenji Watanabe</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">Design Director</p></div>
  <div style="background:var(--color-bg);padding:1.5rem"><div style="aspect-ratio:1/1;background:linear-gradient(145deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 20%,var(--color-bg)));border:1px solid var(--color-border);margin-bottom:1.2rem;display:flex;align-items:center;justify-content:center"><span style="font-size:.6rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.1em">Portrait</span></div><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Maria Santos</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">Technical Director</p></div>
</div>
<style>@media(max-width:700px){.noir-team-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 5: Footer
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">Noir Architects · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '40px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function groundCoffeePage(): array
    {
        return [
            'title' => 'Ground Coffee',
            'slug' => 'ground-coffee',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1.4rem 0;border-bottom:1px solid var(--color-border)">
  <span style="font-family:var(--font-heading);font-weight:600;font-size:1.3rem;letter-spacing:.08em">Ground</span>
  <div style="display:flex;gap:clamp(1rem,2vw,2rem);font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-text-muted)"><span style="color:var(--color-primary)">Coffees</span><span>Process</span><span>Story</span><span>Shop</span></div>
</nav>
<div style="padding:clamp(5rem,12vh,9rem) 0 clamp(2rem,4vh,3rem);text-align:center">
  <h1 style="font-family:var(--font-heading);font-size:clamp(3.5rem,12vw,8rem);font-weight:600;line-height:.85;letter-spacing:.06em;margin:0 0 1.4rem;color:var(--color-heading)">GROUND</h1>
  <p style="font-family:var(--font-heading);font-size:clamp(1rem,1.4vw,1.25rem);color:var(--color-text-muted);margin:0 0 .8rem;font-weight:400">Specialty coffee, roasted with intention.</p>
  <p style="font-size:.92rem;color:var(--color-text-muted);max-width:44ch;margin:0 auto;line-height:1.55;opacity:.7">Single-origin beans sourced directly from producers in Ethiopia, Colombia, and Guatemala.</p>
</div>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '900px']),

                // Section 2: Products
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 2rem">— Current coffees</p>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--color-border)" class="ground-prod-grid">
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(145deg,#3e2b1a,#5c3d24,#8b6f47);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:#f4efe6">Sidamo</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Ethiopia · Washed</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .5rem;line-height:1.1">Sidamo</h3><p style="color:var(--color-text-muted);font-size:.85rem;margin:0;line-height:1.5">Notes of bergamot, jasmine, dark honey</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(160deg,#4a3020,#6b4830,#9a7550);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:#f4efe6">Huila</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Colombia · Natural</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .5rem;line-height:1.1">Huila</h3><p style="color:var(--color-text-muted);font-size:.85rem;margin:0;line-height:1.5">Notes of red cherry, cacao, brown sugar</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(135deg,#453222,#6e5035,#a68a5a);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:#f4efe6">Antigua</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Guatemala · Honey</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .5rem;line-height:1.1">Antigua</h3><p style="color:var(--color-text-muted);font-size:.85rem;margin:0;line-height:1.5">Notes of almond, tangerine, milk chocolate</p></div></div>
</div>
<style>@media(max-width:700px){.ground-prod-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: Process
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 1rem">— Process</p>
<h2 style="font-family:var(--font-heading);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:600;line-height:1;margin:0 0 2.5rem">From seed to cup</h2>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid var(--color-border)" class="ground-process-grid">
  <div style="border-right:1px solid var(--color-border);padding:1.8rem"><p style="font-family:var(--font-heading);font-size:2rem;font-weight:600;color:var(--color-primary);margin:0 0 .8rem;line-height:1">01</p><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .5rem">Source</h3><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">Direct relationships with producers. We visit farms, cup samples, and commit before harvest.</p></div>
  <div style="border-right:1px solid var(--color-border);padding:1.8rem"><p style="font-family:var(--font-heading);font-size:2rem;font-weight:600;color:var(--color-primary);margin:0 0 .8rem;line-height:1">02</p><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .5rem">Roast</h3><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">Small-batch roasting that develops flavour without hiding origin character. Light to medium profiles.</p></div>
  <div style="border-right:1px solid var(--color-border);padding:1.8rem"><p style="font-family:var(--font-heading);font-size:2rem;font-weight:600;color:var(--color-primary);margin:0 0 .8rem;line-height:1">03</p><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .5rem">Rest</h3><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">Every roast rests 10-14 days before shipping. Freshness matters, but so does letting CO₂ settle.</p></div>
  <div style="padding:1.8rem"><p style="font-family:var(--font-heading);font-size:2rem;font-weight:600;color:var(--color-primary);margin:0 0 .8rem;line-height:1">04</p><h3 style="font-family:var(--font-heading);font-size:1rem;font-weight:600;margin:0 0 .5rem">Brew</h3><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">Every bag includes brew guidance. No pretension — just ratios, temperature, and time that work.</p></div>
</div>
<style>@media(max-width:800px){.ground-process-grid{grid-template-columns:repeat(2,1fr) !important}}@media(max-width:500px){.ground-process-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 4: Story
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="max-width:640px">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 1rem">— Our story</p>
  <p style="font-size:clamp(1.05rem,1.3vw,1.2rem);line-height:1.65;color:var(--color-text-muted);margin:0">We started Ground in 2019 with a single-group espresso machine and a belief that good coffee shouldn\'t require a dictionary to enjoy.</p>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 5: Shop CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Current offerings', 'level' => 'h2']),
                            $this->block('button', ['text' => 'Browse coffees', 'url' => '#', 'style' => 'primary']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '900px']),

                // Section 6: Footer
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">Ground Coffee · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '40px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function solsticeRecordsPage(): array
    {
        return [
            'title' => 'Solstice Records',
            'slug' => 'solstice-records',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="min-height:100vh;display:flex;flex-direction:column">
<nav style="display:flex;justify-content:space-between;align-items:center;padding:1.4rem 0;border-bottom:1px solid var(--color-border)">
  <span style="font-family:var(--font-heading);font-weight:600;font-size:1.3rem;letter-spacing:.12em">SOLSTICE</span>
  <div style="display:flex;gap:clamp(1rem,2vw,2rem);font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-text-muted)"><span style="color:#c0392b">Releases</span><span>Artists</span><span>About</span><span>Contact</span></div>
</nav>
<div style="padding:clamp(5rem,14vh,10rem) 0 clamp(2rem,4vh,3rem)">
  <h1 style="font-family:var(--font-heading);font-size:clamp(4rem,14vw,10rem);font-weight:600;line-height:.82;letter-spacing:.06em;margin:0 0 1.6rem;color:var(--color-heading)">SOLSTICE</h1>
  <p style="font-family:var(--font-heading);font-size:.88rem;color:var(--color-text-muted);letter-spacing:.08em;margin:0">Independent label · Est. 2018 · London</p>
</div>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '900px']),

                // Section 2: Current releases
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 2rem">— Current releases</p>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--color-border)" class="sol-releases-grid">
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(135deg,#e8e6e1,#c0beb9);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#555">Cover art</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:#c0392b;margin:0 0 .5rem">Electronic · 2024</p><h3 style="font-family:var(--font-heading);font-size:1.15rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Midnight Architecture</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0 0 .6rem">Pale Circuits</p><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">Eight studies in synthetic texture and negative space.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(180deg,#d4d2cd,#bab8b3);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#555">Cover art</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:#c0392b;margin:0 0 .5rem">Post-rock · 2024</p><h3 style="font-family:var(--font-heading);font-size:1.15rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Letters to No One</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0 0 .6rem">The Outer</p><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">A 40-minute drift through guitar fog and tape loops.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:1/1;background:linear-gradient(200deg,#dddbd6,#c8c6c1);display:flex;align-items:center;justify-content:center"><span style="font-family:var(--font-heading);font-size:.6rem;text-transform:uppercase;letter-spacing:.15em;color:#555">Cover art</span></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:#c0392b;margin:0 0 .5rem">Jazz · 2024</p><h3 style="font-family:var(--font-heading);font-size:1.15rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Seed / Soil</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0 0 .6rem">Amara Diallo</p><p style="color:var(--color-text-muted);font-size:.82rem;margin:0;line-height:1.5">West African polyrhythm meets London jazz club energy.</p></div></div>
</div>
<style>@media(max-width:700px){.sol-releases-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: Philosophy
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="max-width:640px;padding:clamp(2rem,5vh,4rem) 0">
  <blockquote style="border-left:2px solid #c0392b;padding-left:1.5rem;margin:0;font-family:var(--font-heading);font-size:clamp(1.1rem,2vw,1.5rem);font-weight:400;line-height:1.3;color:var(--color-heading)">We sign artists who make music that sounds like it was made in a room, not on a screen.</blockquote>
  <p style="color:var(--color-text-muted);line-height:1.65;margin:1.5rem 0 0">Every release is limited to 500 vinyl and unlimited digital. We don\'t do playlists.</p>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 4: Upcoming
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div>
<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted);margin:0 0 1.5rem">— Next from Solstice</p>
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--color-border)" class="sol-upcoming-grid">
  <div style="background:var(--color-bg);padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:#c0392b;margin:0 0 .5rem">Autumn 2024</p><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Glass Hours</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">by Meridian Signal · Ambient</p></div>
  <div style="background:var(--color-bg);padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:#c0392b;margin:0 0 .5rem">Winter 2024</p><h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:600;margin:0 0 .3rem;line-height:1.1">Catalogue of Echoes</h3><p style="font-size:.82rem;color:var(--color-text-muted);margin:0">by Yara Kessler · Contemporary classical</p></div>
</div>
<style>@media(max-width:500px){.sol-upcoming-grid{grid-template-columns:1fr !important}}</style>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '900px']),

                // Section 5: Contact
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border-top:1px solid var(--color-border);padding-top:2rem">
  <p style="color:var(--color-text-muted);font-size:.92rem;margin:0;line-height:1.6">Demos to <a href="mailto:demos@solstice.fm" style="color:var(--color-heading);text-decoration:underline">demos@solstice.fm</a> · Distribution via Kudos</p>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 6: Footer
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<footer style="border-top:1px solid var(--color-border);padding:2rem 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><span style="font-family:var(--font-heading);font-size:.78rem;color:var(--color-text-muted)">Solstice Records · Published with Stillopress</span><a href="/examples" style="font-family:var(--font-heading);font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-primary);text-decoration:none">← Back to examples</a></footer>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '40px', 'max_width' => '1320px']),
            ],
        ];
    }

    private function templatesPage(): array
    {
        return [
            'title' => 'Templates',
            'slug' => 'templates',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Templates</p>']),
                            $this->block('heading', ['text' => 'Start with structure, not a blank page.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Every template is a real site skeleton built from the same blocks you\'ll use to customize it. Pick one, edit everything, publish.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Template cards
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--color-border)" class="tpl-grid">
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:var(--color-bg);border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;padding:2rem"><div style="text-align:center"><div style="width:40px;height:1px;background:var(--color-border);margin:0 auto .6rem"></div><div style="font-family:var(--font-heading);font-size:.7rem;text-transform:uppercase;letter-spacing:.15em;color:var(--color-text-muted)">Blank canvas</div></div></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Minimal</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Blank</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">A clean canvas. One page, zero assumptions. For people who want to build from scratch.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:var(--color-bg-alt);border-bottom:1px solid var(--color-border);display:flex;align-items:flex-start;padding:1.2rem;gap:.6rem"><div style="flex:1"><div style="height:6px;background:var(--color-heading);width:60%;margin-bottom:.4rem"></div><div style="height:3px;background:var(--color-border);width:90%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-border);width:75%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-border);width:80%;margin-bottom:.8rem"></div><div style="height:3px;background:var(--color-border);width:85%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-border);width:70%"></div></div><div style="width:30%;border-left:1px solid var(--color-border);padding-left:.6rem"><div style="font-size:.45rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted);margin-bottom:.3rem">Archive</div><div style="height:3px;background:var(--color-border);width:80%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-primary);width:60%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-border);width:70%"></div></div></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Editorial</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Blog</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">Posts, categories, archive pages, and an editorial homepage. Ready to write.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:linear-gradient(135deg,var(--color-bg-alt),color-mix(in srgb,var(--color-border) 20%,var(--color-bg)));border-bottom:1px solid var(--color-border);display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:1rem"><div style="background:color-mix(in srgb,var(--color-border) 30%,var(--color-bg));border:1px solid var(--color-border)"></div><div style="background:color-mix(in srgb,var(--color-border) 15%,var(--color-bg));border:1px solid var(--color-border)"></div><div style="background:color-mix(in srgb,var(--color-border) 20%,var(--color-bg));border:1px solid var(--color-border)"></div><div style="background:color-mix(in srgb,var(--color-border) 25%,var(--color-bg));border:1px solid var(--color-border)"></div></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Creative</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Portfolio</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">Gallery layouts, project pages, and a visual-first homepage. Built for creative work.</p></div></div>
  <div style="background:var(--color-bg);padding:0"><div style="aspect-ratio:16/10;background:var(--color-bg);border-bottom:1px solid var(--color-border);display:flex;flex-direction:column;padding:1rem;gap:.4rem"><div style="height:8px;background:var(--color-heading);width:50%;margin-bottom:.2rem"></div><div style="height:3px;background:var(--color-border);width:70%;margin-bottom:.6rem"></div><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;flex:1"><div style="background:var(--color-bg-alt);border:1px solid var(--color-border);padding:.4rem"><div style="height:3px;background:var(--color-primary);width:40%;margin-bottom:.2rem"></div><div style="height:2px;background:var(--color-border);width:80%"></div></div><div style="background:var(--color-bg-alt);border:1px solid var(--color-border);padding:.4rem"><div style="height:3px;background:var(--color-primary);width:40%;margin-bottom:.2rem"></div><div style="height:2px;background:var(--color-border);width:70%"></div></div><div style="background:var(--color-bg-alt);border:1px solid var(--color-border);padding:.4rem"><div style="height:3px;background:var(--color-primary);width:40%;margin-bottom:.2rem"></div><div style="height:2px;background:var(--color-border);width:60%"></div></div></div></div><div style="padding:1.5rem"><p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.62rem;color:var(--color-primary);margin:0 0 .5rem">Professional</p><h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:600;margin:0 0 .6rem;line-height:1.1">Business</h3><p style="color:var(--color-text-muted);font-size:.88rem;margin:0;line-height:1.5">Services, team, contact, and a professional landing page. Built for companies.</p></div></div>
</div>
<style>@media(max-width:700px){.tpl-grid{grid-template-columns:1fr !important}}</style>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 3: How templates work
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— How templates work</p>']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Templates create real pages with real blocks. Every heading, image, and section is editable. There\'s no template lock-in — once applied, you can change anything.</p>']),
                            $this->block('spacer', ['height' => '24px']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">All templates are free and included in Stillopress Core.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '80px', 'max_width' => '900px']),
            ],
        ];
    }

    private function wordpressMigrationPage(): array
    {
        return [
            'title' => 'WordPress Migration',
            'slug' => 'wordpress-migration',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— WordPress Migration</p>']),
                            $this->block('heading', ['text' => 'Leave WordPress without leaving your work behind.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Stillopress imports your WordPress content and maps it to structured blocks. Your posts, categories, images, and Gutenberg layouts come with you.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: What migrates (2-column)
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('heading', ['text' => 'What comes over', 'level' => 'h2']),
                            $this->block('html-embed', ['html' => '<ul style="list-style:none;padding:0;margin:0">
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Posts and pages</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Categories and tags</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Featured images</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Gutenberg blocks</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Author metadata</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem">Publication dates</li>
<li style="padding:.6rem 0;font-size:.92rem">Media library</li>
</ul>']),
                        ]),
                        $this->column([
                            $this->block('heading', ['text' => 'How it works', 'level' => 'h2']),
                            $this->block('html-embed', ['html' => '<ol style="list-style:none;padding:0;margin:0;counter-reset:steps">
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem;counter-increment:steps;display:flex;gap:.8rem;align-items:baseline"><span style="font-family:var(--font-heading);font-weight:600;color:var(--color-primary);font-size:.82rem">01</span> Export your WordPress site as a WXR file</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem;display:flex;gap:.8rem;align-items:baseline"><span style="font-family:var(--font-heading);font-weight:600;color:var(--color-primary);font-size:.82rem">02</span> Open the Stillopress importer</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem;display:flex;gap:.8rem;align-items:baseline"><span style="font-family:var(--font-heading);font-weight:600;color:var(--color-primary);font-size:.82rem">03</span> Upload the WXR file</li>
<li style="padding:.6rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem;display:flex;gap:.8rem;align-items:baseline"><span style="font-family:var(--font-heading);font-weight:600;color:var(--color-primary);font-size:.82rem">04</span> Review the mapped content</li>
<li style="padding:.6rem 0;font-size:.92rem;display:flex;gap:.8rem;align-items:baseline"><span style="font-family:var(--font-heading);font-weight:600;color:var(--color-primary);font-size:.82rem">05</span> Publish to your new static site</li>
</ol>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 3: Gutenberg mapping
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Gutenberg mapping</p>']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Stillopress maps Gutenberg blocks to native blocks automatically. Paragraphs become paragraphs. Headings become headings. Images, galleries, quotes, lists, and columns all map to their Stillopress equivalents. Custom blocks are preserved as HTML embeds.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 4: What changes
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— What changes</p>']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Your WordPress site runs on PHP and MySQL. Your Stillopress site publishes as static HTML. That means: no plugin vulnerabilities, no database on the public server, no WordPress updates, no hosting costs for PHP. Your site loads faster, costs less, and has no attack surface.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 5: Before/after comparison
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:.9rem;min-width:500px">
<thead>
<tr style="border-bottom:2px solid var(--color-text)">
  <th style="text-align:left;padding:1rem;font-family:var(--font-heading);font-weight:600"></th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600;color:var(--color-text-muted)">WordPress</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600;color:var(--color-primary)">Stillopress</th>
</tr>
</thead>
<tbody>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem;font-weight:500">Server runtime</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">PHP</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">None</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem;font-weight:500">Database</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">MySQL</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">None (static)</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem;font-weight:500">Updates</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Monthly patches</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">No public code to patch</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem;font-weight:500">Plugins</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Average 12</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">Zero</td></tr>
<tr><td style="padding:.8rem 1rem;font-weight:500">Page load</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">2.4s average</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">&lt;100ms (static CDN)</td></tr>
</tbody>
</table>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '20px', 'padding_bottom' => '60px', 'max_width' => '900px']),

                // Section 6: CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('button', ['text' => 'Start your migration', 'url' => '/contact', 'style' => 'primary']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '80px', 'max_width' => '900px']),
            ],
        ];
    }

    private function securityPage(): array
    {
        return [
            'title' => 'Security',
            'slug' => 'security',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Security</p>']),
                            $this->block('heading', ['text' => 'The safest website is the one with nothing to hack.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,5rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Stillopress publishes static HTML files. Your public site has no server application, no database, and no plugin ecosystem. That architecture eliminates most web security risks by design.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Two-tier security model
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);padding:clamp(1.5rem,3vw,2.5rem)">
<h2 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 1.5rem;line-height:1.1">Published site</h2>
<ul style="list-style:none;padding:0;margin:0">
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Static HTML files only</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> No server-side code execution</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> No database connections</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> No plugin or extension ecosystem</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> No CMS authentication on public site</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Content Security Policy headers</li>
<li style="padding:.5rem 0;font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> HTTP Strict Transport Security</li>
</ul>
</div>']),
                        ]),
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);padding:clamp(1.5rem,3vw,2.5rem)">
<h2 style="font-family:var(--font-heading);font-size:1.3rem;font-weight:600;margin:0 0 1.5rem;line-height:1.1">CMS studio</h2>
<ul style="list-style:none;padding:0;margin:0">
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Network isolation (admin on separate origin)</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Role-based access control (RBAC)</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> PostgreSQL Row-Level Security (RLS) per tenant</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Session cookies (HttpOnly, Secure, SameSite)</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> HTML sanitization on every block render</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> MIME-type validated uploads</li>
<li style="padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> CSRF protection on all mutations</li>
<li style="padding:.5rem 0;font-size:.88rem;display:flex;align-items:baseline;gap:.6rem"><span style="color:var(--color-primary);font-weight:600;font-size:.72rem">&#10003;</span> Rate limiting on public endpoints</li>
</ul>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 3: Why static is safer
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Why static is safer</p>']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Most CMS security breaches exploit the same attack surface: a server-side application that processes requests, connects to a database, and runs plugins from unknown authors. Stillopress removes that entire surface from the public site. The studio runs on an isolated private domain with defense-in-depth from the network layer to the database row.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 4: Comparison table
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['html' => '<div style="border:1px solid var(--color-border);overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:.9rem;min-width:500px">
<thead>
<tr style="border-bottom:2px solid var(--color-text)">
  <th style="text-align:left;padding:1rem;font-family:var(--font-heading);font-weight:600">Attack surface</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600;color:var(--color-text-muted)">Traditional CMS</th>
  <th style="text-align:center;padding:1rem;font-family:var(--font-heading);font-weight:600;color:var(--color-primary)">Stillopress</th>
</tr>
</thead>
<tbody>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Server-side code</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Exposed</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">None on public site</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Database</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Public-facing</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">Isolated (studio only)</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Plugin ecosystem</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Third-party code</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">No plugins</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">Authentication</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">On public domain</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">Separate origin</td></tr>
<tr style="border-bottom:1px solid var(--color-border)"><td style="padding:.8rem 1rem">File uploads</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">Executable path risk</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">MIME-validated, static only</td></tr>
<tr><td style="padding:.8rem 1rem">Admin exposure</td><td style="text-align:center;padding:.8rem;color:var(--color-text-muted)">/wp-admin on same domain</td><td style="text-align:center;padding:.8rem;color:var(--color-primary)">Network-isolated studio</td></tr>
</tbody>
</table>
</div>']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '60px', 'max_width' => '1100px']),

                // Section 5: Responsible disclosure
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Responsible disclosure</p>']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.05rem,1.3vw,1.35rem);line-height:1.55;color:var(--color-text-muted);max-width:52ch">Found a vulnerability? Contact <a href="mailto:security@stillopress.com">security@stillopress.com</a>. We respond within 24 hours and fix confirmed issues within 72 hours.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 6: CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('button', ['text' => 'Back to Stillopress', 'url' => '/', 'style' => 'outline']),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '80px', 'max_width' => '900px']),
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
