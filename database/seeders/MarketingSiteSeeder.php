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

        $pages = [
            $this->homePage(),
            $this->featuresPage(),
            $this->aboutPage(),
            $this->demosPage(),
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
            'title' => 'Home',
            'slug' => 'home',
            'blocks' => [
                // Section 1: Hero
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('html-embed', ['content' => '<div style="position:relative;padding:clamp(3rem,7vh,7rem) 0 clamp(3.5rem,8vh,8rem);overflow:hidden">
<svg style="position:absolute;right:clamp(-6rem,-2vw,-1rem);top:clamp(1rem,4vh,4rem);width:clamp(20rem,44vw,50rem);opacity:.92;pointer-events:none" viewBox="0 0 400 400" aria-hidden="true">
<defs><filter id="ink" x="-20%" y="-20%" width="140%" height="140%"><feTurbulence type="fractalNoise" baseFrequency="0.018" numOctaves="2" seed="7" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="9"/></filter></defs>
<path class="enso-draw" filter="url(#ink)" d="M146,344 A156,156 0 1 1 254,344" fill="none" stroke="var(--color-primary,#de2e17)" stroke-width="26" stroke-linecap="round" pathLength="100" style="stroke-dasharray:100;stroke-dashoffset:0"/>
</svg>
</div>']),
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— The static-publish CMS</p>']),
                            $this->block('heading', ['text' => 'Build. Publish. Own.', 'level' => 'h1', 'fontSize' => 'clamp(3.4rem,12.5vw,11rem)', 'fontWeight' => '600', 'letterSpacing' => '-.025em']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;color:var(--color-heading);max-width:42ch">Edit in a calm, block-based studio. Publish flat, hand-clean HTML to your own host. No runtime, no database on the front end, no lock-in — just files you keep forever.</p>']),
                            $this->block('button', ['text' => 'Start free →', 'url' => '/pricing', 'style' => 'primary', 'size' => 'lg']),
                            $this->block('button', ['text' => 'See it live', 'url' => '/demos', 'style' => 'outline', 'size' => 'lg']),
                            $this->block('stats', ['items' => [
                                ['value' => '100/100', 'label' => 'PageSpeed, by construction'],
                                ['value' => '93', 'label' => 'Composable blocks'],
                                ['value' => '0ms', 'label' => 'Server render at request'],
                                ['value' => '100%', 'label' => 'Your HTML, your host'],
                            ], 'columns' => 4]),
                        ]),
                    ]),
                ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '100%']),

                // Section 2: Value Props
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Why Stillopress</p>']),
                            $this->block('heading', ['text' => 'A CMS should get out of the reader\'s way.', 'level' => 'h2', 'fontSize' => 'clamp(1.9rem,4.2vw,3.4rem)']),
                        ]),
                    ]),
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => '01 · Own your output', 'level' => 'h3', 'fontSize' => 'clamp(1.3rem,2.2vw,1.9rem)']),
                            $this->block('paragraph', ['content' => '<p>What you publish is plain HTML, CSS and images — content-hashed and sitting on your server. Move it, mirror it, archive it. You are never renting your own website back from us.</p>']),
                            $this->block('divider', []),
                            $this->block('heading', ['text' => '02 · Perfect by construction', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>There is no request-time rendering to slow down, so there is nothing to optimise away. Pages arrive as static files behind a CDN. A 100 PageSpeed score is the floor, not the goal.</p>']),
                            $this->block('divider', []),
                            $this->block('heading', ['text' => '03 · Calm to edit', 'level' => 'h3']),
                            $this->block('paragraph', ['content' => '<p>Compose with blocks in a quiet, focused canvas — or drop into the freeform magazine editor when a page wants to breathe. Preview is the real, published render. No surprises after you ship.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 3: How It Works
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— How it works</p>']),
                            $this->block('heading', ['text' => 'Four steps from blank page to live site.', 'level' => 'h2']),
                        ]),
                    ]),
                    $this->row('1', [
                        $this->column([
                            $this->block('featuregrid', ['columns' => 3, 'items' => [
                                ['icon' => '01', 'title' => 'Stack blocks', 'description' => 'Build pages from 93 typed blocks across nine categories. Nest, reorder, template. Everything is structured content, not a wall of markup.'],
                                ['icon' => '02', 'title' => 'See the real render', 'description' => 'The live preview is the exact static output, framed in an iframe. What you approve is what visitors receive — byte for byte.'],
                                ['icon' => '03', 'title' => 'Atomic swap', 'description' => 'A build renders every page to static HTML, then flips the whole site into place with a single atomic operation. Roll back to any prior snapshot in one move.'],
                                ['icon' => '04', 'title' => 'Keep the files', 'description' => 'The result lives on your host as ordinary files. Take a full export any time. Nothing about your site depends on us staying online.'],
                                ['icon' => '+', 'title' => 'Bring WordPress', 'description' => 'Point the importer at a WXR export. It maps Gutenberg blocks to native blocks, rebuilds your category tree, and re-hosts every attachment.'],
                                ['icon' => '+', 'title' => 'Compose with AI', 'description' => 'Drop in raw text and the AI Page Composer turns it into a real page built from the block system — reviewable, not a black box.'],
                            ]]),
                            $this->block('button', ['text' => 'All features →', 'url' => '/features', 'style' => 'outline']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 4: Security (dark)
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Security</p>']),
                            $this->block('heading', ['text' => 'Six layers between the internet and your content.', 'level' => 'h2', 'color' => '#f4f2ec']),
                        ]),
                    ]),
                    $this->row('1', [
                        $this->column([
                            $this->block('featuregrid', ['columns' => 3, 'gap' => '0', 'items' => [
                                ['icon' => '01', 'title' => 'Network isolation', 'description' => 'Admin and published origins are separated. The editor never shares a host with a visitor.'],
                                ['icon' => '02', 'title' => 'Hashed sessions', 'description' => 'Credentials hashed, sessions carried in HttpOnly cookies. Nothing sensitive touches the browser.'],
                                ['icon' => '03', 'title' => 'RBAC + RLS', 'description' => 'Role-based access enforced again at the database with row-level security per tenant.'],
                                ['icon' => '04', 'title' => 'Sanitised HTML', 'description' => 'Every block of authored markup is purified on render. No script sneaks into a static page.'],
                                ['icon' => '05', 'title' => 'Verified uploads', 'description' => 'Media is checked by true MIME type, not by extension, before it is ever stored.'],
                                ['icon' => '06', 'title' => 'CSP + HSTS', 'description' => 'Strict content policy and enforced transport ship with every published site out of the box.'],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '1320px', 'background_color' => '#121210']),

                // Section 5: CTA (dark)
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'Publish something that stays yours.', 'level' => 'h2', 'textAlign' => 'center', 'color' => '#f4f2ec']),
                            $this->block('paragraph', ['content' => '<p style="text-align:center;color:var(--color-text-muted)">The free plan is a full CMS — every block, static publishing, one site. Nothing expires and nothing is held hostage.</p>']),
                            $this->block('button', ['text' => 'Start free →', 'url' => '/pricing', 'style' => 'primary', 'size' => 'lg']),
                        ]),
                    ]),
                ], ['padding_top' => '80px', 'padding_bottom' => '80px', 'max_width' => '800px', 'background_color' => '#121210']),
            ],
        ];
    }

    private function featuresPage(): array
    {
        return [
            'title' => 'Features',
            'slug' => 'features',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Features</p>']),
                            $this->block('heading', ['text' => 'Everything to build it. Nothing shipped to the reader.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;color:var(--color-heading);max-width:52ch">A complete authoring platform on one side of the wall, and flat static files on the other. Here is what fills the gap.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Feature matrix
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('catalog', ['openFirst' => false, 'imageFilter' => 'none', 'headerLabels' => ['', 'Feature', 'Category', ''], 'items' => [
                                ['title' => '93 blocks, 9 categories', 'subtitle' => 'Composition', 'content' => '<p>Typed, schema-backed blocks that nest and reorder freely. Each block renders identically in the editor and in the final page.</p><ul><li>Structure, media, text, layout</li><li>Nesting via parent references</li><li>Reusable block templates</li><li>Drag, drop, keyboard reorder</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Design-token theme engine', 'subtitle' => 'Theming', 'content' => '<p>Themes are W3C design tokens, resolved and compiled to CSS. Change a token, preview the whole site, publish.</p><ul><li>Theme Studio with live preview</li><li>Token references &amp; inheritance</li><li>Per-site overrides</li><li>Coverage analysis built in</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Atomic static publish', 'subtitle' => 'Publishing', 'content' => '<p>A full build renders to static HTML, then swaps into place in one operation. Every publish is a snapshot you can restore.</p><ul><li>Symlink swap or atomic rename</li><li>Delta rollback to any version</li><li>Content-hashed assets</li><li>Zero request-time rendering</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'SEO that ships itself', 'subtitle' => 'Discovery', 'content' => '<p>The essentials are generated on every publish, not bolted on. Clean, readable URLs by default.</p><ul><li>Sitemaps &amp; robots</li><li>Open Graph metadata</li><li>Clean URL structure</li><li>Semantic static markup</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'AI Page Composer', 'subtitle' => 'Authoring with AI', 'content' => '<p>Paste raw text; get a real page assembled from the block system. Review and edit every block — it is content, not a screenshot.</p><ul><li>Text in, structured page out</li><li>Native blocks, fully editable</li><li>Per-tenant token budgets</li><li>Editorial judgement, not filler</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Dual editors', 'subtitle' => 'Layout', 'content' => '<p>A quiet vertical block editor for most pages, and a freeform magazine canvas when a spread needs to be composed by hand.</p><ul><li>Block editor — focused, linear</li><li>Magazine editor — freeform canvas</li><li>Shared block data model</li><li>Same publish pipeline for both</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Swiss grid system', 'subtitle' => 'Structure', 'content' => '<p>A real four-level hierarchy — section, row, column, module — so pages stay structured instead of becoming a soup of divs.</p><ul><li>Enforced page hierarchy</li><li>Wireframe &amp; visual modes</li><li>Responsive by default</li><li>Consistent, predictable output</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Six-layer security', 'subtitle' => 'Defence', 'content' => '<p>The published site is inert static files. The studio is protected from network to database.</p><ul><li>Isolated admin origin</li><li>RBAC + row-level security</li><li>HTML sanitisation on render</li><li>CSP, HSTS, verified uploads</li></ul>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Import &amp; export', 'subtitle' => 'Portability', 'content' => '<p>Arrive from WordPress without losing your archive; leave with everything whenever you like.</p><ul><li>WordPress WXR import</li><li>Gutenberg block mapping</li><li>Category tree &amp; media re-host</li><li>Full site export, any time</li></ul>', 'contentSecondary' => '', 'images' => []],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '80px', 'max_width' => '1320px']),

                // Section 3: CTA
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('button', ['text' => 'Start free →', 'url' => '/pricing', 'style' => 'primary', 'size' => 'lg']),
                        ]),
                    ]),
                ], ['padding_top' => '60px', 'padding_bottom' => '60px', 'max_width' => '800px']),
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— About</p>']),
                            $this->block('heading', ['text' => 'Stillness in the studio. Precision on the wire.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Manifesto
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('heading', ['text' => 'A website should be calm to make and quiet to serve.', 'level' => 'h2', 'fontSize' => 'clamp(1.6rem,3.6vw,2.9rem)', 'fontWeight' => '400']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1320px']),

                // Section 3: Story
                $this->section([
                    $this->row('1/2+1/2', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5">Stillopress began with a frustration: modern content platforms are loud. They ship a runtime, a database and a framework to every reader, then spend the rest of their lives trying to make that fast again.</p>']),
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— What we believe</p>']),
                            $this->block('heading', ['text' => 'A few convictions we build against.', 'level' => 'h2']),
                            $this->block('catalog', ['openFirst' => false, 'imageFilter' => 'none', 'headerLabels' => ['Principle', 'Why it matters', '', ''], 'items' => [
                                ['title' => 'Own your work', 'subtitle' => '', 'content' => '<p>Your website should never depend on a vendor\'s servers staying up, or a plan staying paid. Static output means the thing you publish is genuinely, portably yours.</p>', 'contentSecondary' => '', 'images' => []],
                                ['title' => 'Speed is a floor', 'subtitle' => '', 'content' => '<p>Performance shouldn\'t be a feature you buy or a plugin you install. If there is nothing to render at request time, there is nothing to make slow. We start at a perfect score.</p>', 'contentSecondary' => '', 'images' => []],
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— The build</p>']),
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

    private function demosPage(): array
    {
        return [
            'title' => 'Demos',
            'slug' => 'demos',
            'blocks' => [
                // Section 1: Page head
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Demos</p>']),
                            $this->block('heading', ['text' => 'See what a static site can still do.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;max-width:52ch">Live sites, all published straight from Stillopress. This very site is one of them.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Demo cards
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('featuregrid', ['columns' => 2, 'items' => [
                                ['icon' => '●', 'title' => 'This website', 'description' => 'The marketing site you\'re reading — composed in the block editor, published as static HTML. Editorial · monochrome.'],
                                ['icon' => '●', 'title' => 'The Quarterly', 'description' => 'A freeform magazine issue built on the canvas editor — layered spreads, still flat HTML. Magazine · DTP.'],
                                ['icon' => '●', 'title' => 'Documentation starter', 'description' => 'A clean docs template with sidebar navigation, search-ready structure and code blocks. Docs · reference.'],
                                ['icon' => '●', 'title' => 'Studio portfolio', 'description' => 'A gallery-forward portfolio with a cinematic scroll layout — no framework at runtime. Portfolio · cinematic.'],
                            ]]),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: Block library
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Block library</p>']),
                            $this->block('heading', ['text' => '93 blocks, grouped nine ways.', 'level' => 'h2']),
                            $this->block('paragraph', ['content' => '<p style="color:var(--color-text-muted)">Every demo above is built from the same set. Nothing bespoke, nothing off-menu.</p>']),
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Pricing</p>']),
                            $this->block('heading', ['text' => 'Free is a full CMS. Not a trial.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;max-width:52ch">Every plan publishes real static sites you own. Paid tiers add editors, AI and scale — never the right to keep your own files.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '100px', 'padding_bottom' => '40px', 'max_width' => '900px']),

                // Section 2: Pricing table
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('pricingtable', ['columns' => 3, 'plans' => [
                                ['name' => 'Solo', 'price' => '€0', 'period' => 'forever', 'features' => ['All 93 blocks', 'Block editor', 'Static publish & rollback', 'Design-token themes', 'One site', 'Community docs'], 'ctaText' => 'Start free', 'ctaUrl' => '/contact', 'highlighted' => false],
                                ['name' => 'Maker', 'price' => '€12', 'period' => '/mo', 'features' => ['Everything in Solo', 'Magazine editor', 'AI Page Composer', 'WordPress import', 'Custom domains', 'Up to 5 sites'], 'ctaText' => 'Choose Maker', 'ctaUrl' => '/contact', 'highlighted' => true],
                                ['name' => 'Studio', 'price' => '€49', 'period' => '/mo', 'features' => ['Everything in Maker', 'Roles & RBAC', 'Tenant isolation (RLS)', 'Unlimited sites', 'Priority support & SLA', 'Onboarding session'], 'ctaText' => 'Choose Studio', 'ctaUrl' => '/contact', 'highlighted' => false],
                            ]]),
                            $this->block('paragraph', ['content' => '<p style="font-size:.85rem;color:var(--color-text-muted);margin-top:1rem">Prices shown are placeholders for the reference build — set your real numbers before launch.</p>']),
                        ]),
                    ]),
                ], ['padding_top' => '40px', 'padding_bottom' => '60px', 'max_width' => '1320px']),

                // Section 3: FAQ
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Questions</p>']),
                            $this->block('heading', ['text' => 'The honest answers.', 'level' => 'h2']),
                            $this->block('accordion', ['openFirst' => false, 'items' => [
                                ['title' => 'Do I really own the published site?', 'content' => '<p>Yes — completely. Publishing renders your pages to static HTML, CSS and images on your own host. If you cancelled tomorrow, every file would keep working exactly as it is. You can also export the whole site at any time.</p>'],
                                ['title' => 'How do you guarantee a perfect PageSpeed score?', 'content' => '<p>There\'s nothing to render when a visitor arrives — the page is already a static file served from a CDN. Without request-time database queries or framework hydration, the usual sources of slowness simply aren\'t there.</p>'],
                                ['title' => 'Can I move an existing WordPress site over?', 'content' => '<p>On the Maker plan, point the importer at a WordPress WXR export. It maps Gutenberg blocks to native blocks, rebuilds your category hierarchy, re-hosts your media, and preserves featured images.</p>'],
                                ['title' => 'Do I need a server to use the free plan?', 'content' => '<p>The free plan is self-hosted, so you\'ll bring your own hosting — a small VPS or even shared hosting is enough, since the output is just static files. Paid plans add managed hosting and custom domains.</p>'],
                                ['title' => 'What happens to my site if Stillopress disappears?', 'content' => '<p>It keeps running. Your live site is static files with no dependency on our infrastructure. That independence is the entire point of the product, not a footnote.</p>'],
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Documentation</p>']),
                            $this->block('heading', ['text' => 'Start here.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;max-width:52ch">From first publish to the block API. Everything you need to run Stillopress well.</p>']),
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
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;margin-bottom:2rem">Publish your first static page in under ten minutes. This guide walks the whole loop — compose, preview, publish.</p>']),
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
                            $this->block('paragraph', ['content' => '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.26em;font-weight:500;font-size:.78rem;color:var(--color-text-muted)">— Contact</p>']),
                            $this->block('heading', ['text' => 'Say hello.', 'level' => 'h1', 'fontSize' => 'clamp(2.7rem,7.5vw,6.2rem)']),
                            $this->block('paragraph', ['content' => '<p style="font-size:clamp(1.15rem,1.4vw,1.5rem);line-height:1.5;max-width:52ch">Questions about the product, a plan, or moving your site over. We read everything.</p>']),
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
