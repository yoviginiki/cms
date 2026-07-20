<?php

namespace Database\Seeders;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dogfooded user documentation — docs live as CMS pages on a `docs` site so
 * they publish as flat HTML like any tenant content (and feed future RAG).
 *
 * Idempotent: pages are created-or-updated by slug; re-running refreshes
 * content without duplicating. Each feature slice extends pages() with its
 * own guide. Content uses only theme-agnostic semantic blocks
 * (heading/paragraph/list/divider) so any theme renders it cleanly.
 *
 * Scale ceilings and limits stated here are the real, tested numbers — when
 * a limit changes in code, update the matching doc text in the same PR.
 */
class DocsSiteSeeder extends Seeder
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
        // Single-tenant deployment convention (same as public media routes):
        // the docs site belongs to the first tenant.
        $tenant = Tenant::query()->orderBy('created_at')->first();
        if (!$tenant) {
            $this->command?->warn('DocsSiteSeeder: no tenant exists; skipping.');

            return;
        }
        DB::statement("SET app.current_tenant_id = '{$tenant->id}'");

        $site = Site::query()->where('tenant_id', $tenant->id)->where('slug', 'docs')->first()
            ?? Site::create([
                'tenant_id' => $tenant->id,
                'name' => 'Stillopress Docs',
                'slug' => 'docs',
                'status' => 'active',
                'seo_defaults' => ['title_template' => '{title} — Stillopress Docs'],
                'settings' => ['auto_publish' => false],
            ]);

        foreach ($this->pages() as $def) {
            $this->resetCounters();
            $page = $site->pages()->where('slug', $def['slug'])->first();
            if (!$page) {
                $page = $this->pageService->createPage([
                    'title' => $def['title'],
                    'slug' => $def['slug'],
                    'status' => 'published',
                ], $site);
                $this->command?->info("  Created: {$def['title']}");
            } else {
                $page->update(['title' => $def['title'], 'status' => 'published']);
                $this->command?->info("  Updated: {$def['title']}");
            }
            $this->blockService->syncBlocks($page, $def['blocks']);
        }

        $home = $site->pages()->where('slug', 'home')->first();
        if ($home) {
            $settings = $site->settings ?? [];
            $settings['homepage_id'] = $home->id;
            $settings['homepage_type'] = 'page';
            $site->update(['settings' => $settings]);
        }
    }

    /** @return array<int, array{title: string, slug: string, blocks: array}> */
    private function pages(): array
    {
        return [
            $this->indexPage(),
            $this->collectionsGuide(),
            $this->importGuide(),
            // Later slices append: query guide, search guide, forms guide, wizards guide.
        ];
    }

    // ─── Docs index ─────────────────────────────────────────────────────

    private function indexPage(): array
    {
        return [
            'title' => 'Documentation',
            'slug' => 'home',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->heading('Stillopress documentation', 'h1'),
                            $this->para('User guides for the platform, written and published with the platform itself — every page here is ordinary CMS content, published as flat static HTML.'),
                            $this->divider(),
                            $this->heading('Guides', 'h2'),
                            $this->list([
                                'Collections — structured content: schemas, field types, relations, publishing tiers. See /collections/',
                                'Importing data — CSV and XLSX imports, column mapping, upserts, relation matching. See /importing-data/',
                            ]),
                            $this->para('More guides land as features ship: queries, search, forms, and the app wizards.'),
                        ]),
                    ]),
                ]),
            ],
        ];
    }

    // ─── Collections guide ──────────────────────────────────────────────

    private function collectionsGuide(): array
    {
        return [
            'title' => 'Collections',
            'slug' => 'collections',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->heading('Collections', 'h1'),
                            $this->para('Collections hold structured records — products, books, parts, listings — with a schema you define per collection. Records publish as ordinary flat pages, so a catalog of 500 items is 500 static HTML files, not a database call per visit.'),

                            $this->heading('Defining a schema', 'h2'),
                            $this->para('Create a collection under <strong>Collections → New</strong>, then add fields in the schema editor. Every record needs a title field; everything else is up to you.'),
                            $this->para('Available field types:'),
                            $this->list([
                                'Basics — text, rich text, number, price, boolean, date',
                                'Choices — select, multi-select (fixed option lists; good for facets)',
                                'Media — image, gallery, file (stored as asset references)',
                                'Contact — email, URL, phone',
                                'Advanced — SKU (normalized to uppercase, good as a unique key), relation (links records across collections)',
                            ]),
                            $this->para('Per-field flags: <strong>required</strong>, <strong>unique</strong> (checked on save; concurrent imports can race — use SKU keys for imports), <strong>searchable</strong> (feeds the search index), <strong>facetable</strong> (become filter options), and <strong>show in list</strong> (admin table columns).'),

                            $this->heading('Relations', 'h2'),
                            $this->para('A relation field links to records of another (or the same) collection, in <strong>one</strong> mode (a single linked record) or <strong>many</strong> mode (an ordered list). Many-mode relations can carry typed <strong>pivot fields</strong> on the link itself — for example a supplier relation holding that supplier\'s part number and price. Up to 10 pivot fields, scalar types only.'),

                            $this->heading('Hierarchy (category trees)', 'h2'),
                            $this->para('A collection can be a tree — categories with subcategories, for example. In the schema editor choose <strong>Make hierarchical</strong> (or point Hierarchy at any relation field that links the collection to itself in one mode). Each record then has a Parent picker, the records list shows the tree, and published URLs follow it: /categories/painting/oil/. Loops are rejected on save, trees are capped at 6 levels, and moving or renaming a category automatically republishes everything beneath it.'),
                            $this->para('On a published category page the built-in layout adds a breadcrumb trail and a list of subcategories. In your own record templates, use the Record Loop block with source <em>Children of current record</em> for subcategories, or <em>Records linking to current record</em> to list, say, all products filed under the category being viewed.'),

                            $this->heading('Publishing tiers', 'h2'),
                            $this->para('Each collection has a tier that decides how its data reaches visitors. Both tiers publish flat record pages; the difference is how search and filtering work.'),
                            $this->list([
                                'Static (default) — everything is flat files. A JSON search index publishes next to the records and the browser filters it locally. Best up to roughly 2,000 records; the admin shows an advisory warning past that.',
                                'Dynamic — for large datasets. Record pages stay static for SEO, but search and filtering query a read-only, cached, rate-limited API. Measured at 20,000 records with 40,000 relation rows: cold queries 60–110 ms, cached reads under 1 ms.',
                            ]),
                            $this->para('Switching tier is just a setting plus a republish — the search blocks on your pages adapt automatically.'),

                            $this->heading('Published output', 'h2'),
                            $this->list([
                                'Record pages publish at /{collection}/{record-slug}/ (prefix configurable per collection)',
                                'Archive pages paginate statically at /{collection}/ and /{collection}/page/2/ … (6–100 records per page, default 24)',
                                'Design record pages with a Record Single template, or rely on the built-in fallback layout',
                                'Records appear in the sitemap automatically',
                            ]),

                            $this->heading('Search on the published site', 'h2'),
                            $this->para('Add the Search Box, Facet Filter and Results Grid blocks to any page. On static-tier collections they load a sharded JSON index (shards stay under ~2.5 MB raw, fetched only when a visitor starts searching); on dynamic-tier they call the public API. Search needs JavaScript, but the page itself does not: the archive listing and facet options render as plain HTML, and a notice points no-JS visitors to the full list. Results render up to 120 cards before asking the visitor to refine.'),

                            $this->heading('Staying fresh', 'h2'),
                            $this->para('Editing a record marks exactly the affected output stale — its own page, the collection archive and index, and any page whose blocks list that collection. Republish stale content from the publish panel, or enable auto-republish in site settings to rebuild automatically on save. Deleting a record or collection that other content still references is blocked with a clear list of what uses it.'),

                            $this->heading('Honest limits', 'h2'),
                            $this->list([
                                'Static tier is advisory-capped at ~2,000 records — beyond that, switch to dynamic',
                                'Hierarchies are capped at 6 levels; a category rename republishes descendants but the old pages linger until the next full publish',
                                'Unique checks run at save time, not as database constraints — a simultaneous import race can slip a duplicate through',
                                'Relation traversal in queries is one hop deep (e.g. product → supplier field)',
                                'Public API reads are capped at 50 records per request and rate-limited per IP',
                            ]),
                        ]),
                    ]),
                ]),
            ],
        ];
    }

    // ─── Import guide ───────────────────────────────────────────────────

    private function importGuide(): array
    {
        return [
            'title' => 'Importing data (CSV & XLSX)',
            'slug' => 'importing-data',
            'blocks' => [
                $this->section([
                    $this->row('1', [
                        $this->column([
                            $this->heading('Importing data', 'h1'),
                            $this->para('Bring existing data into a collection from a CSV or Excel (.xlsx) file: upload, map columns to fields, preview, run. Imports stream row-by-row, so large files don\'t exhaust memory.'),

                            $this->heading('Upload', 'h2'),
                            $this->list([
                                'Formats: .csv (comma, semicolon or tab delimited — detected automatically) and .xlsx (first sheet only)',
                                'Maximum file size: 50 MB',
                                'The first row must be column headers',
                                'After upload you see the headers and the first 20 rows as a preview',
                            ]),

                            $this->heading('Mapping columns', 'h2'),
                            $this->para('Match each file column to a collection field — columns whose header matches a field name are pre-matched for you. Unmapped columns are ignored. Values are validated per field type exactly like manual entry: prices are rounded, SKUs normalized to uppercase, URLs must be http(s), dates accepted in common formats.'),
                            $this->para('Excel date cells arrive as real dates and are stored as YYYY-MM-DD. One caveat: a date cell with no date formatting in the spreadsheet reads as Excel\'s internal day number and fails that row with a clear error — format the column as a date in your spreadsheet before exporting.'),

                            $this->heading('Insert or update', 'h2'),
                            $this->list([
                                'Insert — every row becomes a new record',
                                'Update by key — pick a unique field (typically SKU/ISBN); rows matching an existing record update it, everything else inserts. This is the supplier-price-refresh path: re-import the same file with new prices and only the prices change.',
                            ]),

                            $this->heading('Relations by name', 'h2'),
                            $this->para('A column mapped to a relation field matches related records by slug or title, case-insensitively. Optionally, unknown names auto-create draft records in the target collection so you can fill them in later. Pivot values (fields on the link itself) are not importable from files — set them in the record editor or via the API.'),

                            $this->heading('Errors and results', 'h2'),
                            $this->list([
                                'Choose skip (default: bad rows are reported, good rows import) or halt on first error',
                                'The result screen shows created/updated/failed counts and a per-row error table (first 200 errors kept)',
                                'Row numbers in errors refer to the file, including the header row',
                            ]),

                            $this->heading('Export', 'h2'),
                            $this->para('Every collection exports back to CSV — all fields plus slug and status, with relations as pipe-separated slugs. An export re-imports cleanly, so export → edit in a spreadsheet → re-import with update-by-key is a supported round trip.'),
                        ]),
                    ]),
                ]),
            ],
        ];
    }

    // ─── Block helpers (MarketingSiteSeeder conventions) ────────────────

    private function heading(string $text, string $level): array
    {
        return $this->block('heading', ['text' => $text, 'level' => $level]);
    }

    private function para(string $html): array
    {
        return $this->block('paragraph', ['content' => "<p>{$html}</p>"]);
    }

    private function list(array $items): array
    {
        return $this->block('list', ['items' => $items, 'listType' => 'bullet']);
    }

    private function divider(): array
    {
        return $this->block('divider', []);
    }

    private function section(array $children, array $data = []): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => array_merge(['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '820px'], $data),
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
