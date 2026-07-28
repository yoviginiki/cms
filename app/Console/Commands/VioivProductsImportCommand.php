<?php

namespace App\Console\Commands;

use App\Domain\Assets\Services\AssetService;
use App\Domain\Collections\Services\CollectionService;
use App\Domain\Collections\Services\RecordService;
use App\Models\Asset;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Services\SiteWizard\SiteWizardMediaImporter;
use App\Support\SsrfGuard;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One-off, idempotent importer: turns the vioiv.stillopress.com WordPress
 * product catalogue (post_type=post) into a native Collections "Products"
 * collection on the CMS "vioiv" site — both Polylang languages, with
 * category / subcategory / manufacturer, description, image and PDF.
 *
 * Re-runnable: the collection + fields are created once (options refreshed
 * each run), and records upsert on the unique `source_id` (WP post ID).
 * Media dedupes by checksum, and is skipped when the record already holds an
 * asset for that field unless --refresh-media is passed.
 */
class VioivProductsImportCommand extends Command
{
    protected $signature = 'vioiv:import-products
        {--site=vioiv : Target CMS site UUID or slug}
        {--tenant=019dfba5-a96b-719d-954d-60a4a549f949 : Tenant UUID (RLS)}
        {--wp-db=cytechno_vioiv}
        {--wp-user=cytechno_vioiv}
        {--wp-pass= : WP DB password (default: read from wp-config.php)}
        {--wp-host=127.0.0.1}
        {--wp-prefix=wpv3_}
        {--wp-config=/home/cytechno/web/vioiv.stillopress.com/public_html/wp-config.php}
        {--media-base=https://vioiv.stillopress.com/wp-content/uploads}
        {--lang= : Only import this language (bg|en); default both}
        {--limit=0 : Import at most N products (0 = all) — for testing}
        {--refresh-media : Re-download image/PDF even if the record already has one}
        {--skip-media : Do not import any media (fast structural pass)}';

    protected $description = 'Import the vioiv WordPress product catalogue into a Collections "Products" collection';

    public function handle(
        CollectionService $collections,
        RecordService $records,
        SiteWizardMediaImporter $mediaImporter,
        AssetService $assets,
    ): int {
        // 1. RLS + site
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->option('tenant')]);
        $target = (string) $this->option('site');
        $site = preg_match('/^[0-9a-f-]{36}$/i', $target) === 1
            ? Site::find($target)
            : Site::where('slug', $target)->first();
        if (!$site) {
            $this->error("Site not found: {$target}");

            return self::FAILURE;
        }
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$site->tenant_id]);
        $this->info("Site: {$site->slug} ({$site->id})");

        // 2. WP connection
        $pass = (string) $this->option('wp-pass');
        if ($pass === '') {
            $cfg = @file_get_contents((string) $this->option('wp-config'));
            if ($cfg && preg_match("/'DB_PASSWORD'\s*,\s*'([^']*)'/", $cfg, $m)) {
                $pass = $m[1];
            }
        }
        Config::set('database.connections.vioiv_wp', [
            'driver' => 'mysql',
            'host' => $this->option('wp-host'),
            'database' => $this->option('wp-db'),
            'username' => $this->option('wp-user'),
            'password' => $pass,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $wp = DB::connection('vioiv_wp');
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->option('wp-prefix'));
        $mediaBase = rtrim((string) $this->option('media-base'), '/');

        // 3. Extract product rows from WP
        $rows = $this->extractProducts($wp, $prefix, $mediaBase);
        if ($langOnly = (string) $this->option('lang')) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['language'] === $langOnly));
        }
        if (($limit = (int) $this->option('limit')) > 0) {
            $rows = array_slice($rows, 0, $limit);
        }
        $this->info('Products found in WP: ' . count($rows));

        // 4. Build option sets + ensure collection/schema
        $collection = $this->ensureCollection($collections, $site, $rows);
        $this->info("Collection: {$collection->slug} ({$collection->id})");

        // 5. Upsert records
        $created = 0;
        $updated = 0;
        $imgOk = 0;
        $pdfOk = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $existing = Record::where('collection_id', $collection->id)
                ->whereField('source_id', $row['source_id'])->first();

            $data = [
                'source_id' => $row['source_id'],
                'name' => $row['name'],
                'language' => $row['language'],
                'description' => $row['description'],
            ];
            if ($row['category'] !== null) {
                $data['category'] = $row['category'];
            }
            if ($row['subcategory'] !== null) {
                $data['subcategory'] = $row['subcategory'];
            }
            if ($row['manufacturer'] !== null) {
                $data['manufacturer'] = $row['manufacturer'];
            }

            // Media (skip if already present, unless refreshing)
            if (!$this->option('skip-media')) {
                $keepImg = !$this->option('refresh-media') && is_string($existing?->data['image'] ?? null);
                $keepPdf = !$this->option('refresh-media') && is_string($existing?->data['pdf'] ?? null);

                $imgId = $keepImg ? $existing->data['image'] : null;
                if (!$imgId && $row['image_url']) {
                    $asset = $mediaImporter->fromUrl($site, $row['image_url'], $row['name']);
                    $imgId = $asset?->id;
                }
                if ($imgId) {
                    $data['image'] = $imgId;
                    $imgOk++;
                }

                $pdfId = $keepPdf ? $existing->data['pdf'] : null;
                if (!$pdfId && $row['pdf_url']) {
                    $asset = $this->importPdf($assets, $site, $row['pdf_url'], $row['name']);
                    $pdfId = $asset?->id;
                }
                if ($pdfId) {
                    $data['pdf'] = $pdfId;
                    $pdfOk++;
                }
            } elseif ($existing) {
                foreach (['image', 'pdf'] as $k) {
                    if (is_string($existing->data[$k] ?? null)) {
                        $data[$k] = $existing->data[$k];
                    }
                }
            }

            try {
                $records->save($collection, $site, $existing, [
                    'data' => $data,
                    'status' => 'published',
                ]);
                $existing ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  ! {$row['source_id']} ({$row['name']}): {$e->getMessage()}");
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Done. created={$created} updated={$updated} images={$imgOk} pdfs={$pdfOk}");
        $this->info('Total records now: ' . Record::where('collection_id', $collection->id)->count());

        return self::SUCCESS;
    }

    /**
     * Pull every published product post with its language, category (top
     * group), subcategory (product leaf), manufacturer (brand leaf),
     * description, featured image URL and PDF URL.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractProducts($wp, string $prefix, string $mediaBase): array
    {
        $posts = $wp->table("{$prefix}posts")
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->orderBy('ID')
            ->get(['ID', 'post_title', 'post_content', 'post_excerpt', 'post_name']);

        $rows = [];
        foreach ($posts as $p) {
            // Language via the Polylang `language` taxonomy (term slug bg|en).
            $lang = $wp->table("{$prefix}term_relationships as tr")
                ->join("{$prefix}term_taxonomy as tt", function ($j) {
                    $j->on('tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('tt.taxonomy', 'language');
                })
                ->join("{$prefix}terms as t", 't.term_id', '=', 'tt.term_id')
                ->where('tr.object_id', $p->ID)
                ->value('t.slug');
            if (!in_array($lang, ['bg', 'en'], true)) {
                continue; // only the two catalogue languages
            }

            // Category terms this post carries (each has parent group id).
            $cats = $wp->table("{$prefix}term_relationships as tr")
                ->join("{$prefix}term_taxonomy as tt", function ($j) {
                    $j->on('tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('tt.taxonomy', 'category');
                })
                ->join("{$prefix}terms as t", 't.term_id', '=', 'tt.term_id')
                ->where('tr.object_id', $p->ID)
                ->get(['t.name', 'tt.parent', 't.term_id']);

            // Product branch roots: Продукти(1204)/Products(1208).
            // Manufacturer branch roots: Производители(1206)/Brands(1211).
            $productRoots = [1204, 1208];
            $brandRoots = [1206, 1211];
            $category = null;      // top group (Продукти / Products)
            $subcategory = null;   // product leaf (Компресори …)
            $manufacturer = null;  // brand leaf (BITZER …)
            foreach ($cats as $c) {
                $parent = (int) $c->parent;
                if (in_array($parent, $productRoots, true)) {
                    $subcategory = $this->decode($c->name);
                    $category = $parent === 1204 ? 'Продукти' : 'Products';
                } elseif (in_array($parent, $brandRoots, true)) {
                    $manufacturer = $this->decode($c->name);
                }
            }
            // Fallback manufacturer from the `brand` postmeta.
            if ($manufacturer === null) {
                $brand = $wp->table("{$prefix}postmeta")->where('post_id', $p->ID)
                    ->where('meta_key', 'brand')->value('meta_value');
                if (is_string($brand) && trim($brand) !== '') {
                    $manufacturer = $this->decode(trim($brand));
                }
            }

            // Skip non-products (blog articles, catalog landing pages) — a
            // real product sits under the product and/or manufacturer branch.
            if ($subcategory === null && $manufacturer === null) {
                continue;
            }

            // Featured image → uploads path.
            $imageUrl = null;
            $thumbId = $wp->table("{$prefix}postmeta")->where('post_id', $p->ID)
                ->where('meta_key', '_thumbnail_id')->value('meta_value');
            if ($thumbId) {
                $file = $wp->table("{$prefix}postmeta")->where('post_id', $thumbId)
                    ->where('meta_key', '_wp_attached_file')->value('meta_value');
                if ($file) {
                    $imageUrl = "{$mediaBase}/{$file}";
                }
            }

            // PDF (direct meta URL).
            $pdfUrl = $wp->table("{$prefix}postmeta")->where('post_id', $p->ID)
                ->where('meta_key', 'pdf_url')->value('meta_value');
            $pdfUrl = (is_string($pdfUrl) && str_starts_with($pdfUrl, 'http')) ? $pdfUrl : null;

            $rows[] = [
                'source_id' => (string) $p->ID,
                'name' => $this->decode($p->post_title) ?: "Product {$p->ID}",
                'language' => $lang,
                'category' => $category,
                'subcategory' => $subcategory,
                'manufacturer' => $manufacturer,
                'description' => $this->cleanDescription((string) $p->post_content),
                'image_url' => $imageUrl,
                'pdf_url' => $pdfUrl,
            ];
        }

        return $rows;
    }

    private function ensureCollection(CollectionService $collections, Site $site, array $rows): ContentCollection
    {
        $catOpts = $this->distinct($rows, 'category');
        $manOpts = $this->distinct($rows, 'manufacturer');
        if ($catOpts === []) {
            $catOpts = ['Продукти', 'Products'];
        }
        if ($manOpts === []) {
            $manOpts = ['—'];
        }

        $schema = [
            'title_field' => 'name',
            'slug_source' => 'name',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true, 'show_in_list' => true],
                ['key' => 'source_id', 'label' => 'Source ID', 'type' => 'text', 'unique' => true],
                ['key' => 'language', 'label' => 'Language', 'type' => 'select', 'facetable' => true, 'show_in_list' => true, 'options' => ['bg', 'en']],
                ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'facetable' => true, 'options' => $catOpts],
                ['key' => 'subcategory', 'label' => 'Subcategory', 'type' => 'text', 'searchable' => true, 'show_in_list' => true],
                ['key' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'select', 'facetable' => true, 'show_in_list' => true, 'options' => $manOpts],
                ['key' => 'description', 'label' => 'Description', 'type' => 'rich_text', 'searchable' => true],
                ['key' => 'image', 'label' => 'Image', 'type' => 'image'],
                ['key' => 'pdf', 'label' => 'PDF', 'type' => 'file'],
            ],
        ];

        $existing = ContentCollection::where('site_id', $site->id)->where('slug', 'products')->first();
        if ($existing) {
            $collections->update($existing, $site, ['name' => $existing->name, 'slug' => 'products', 'schema' => $schema]);

            return $existing->refresh();
        }

        return $collections->create($site, [
            'name' => 'Products',
            'slug' => 'products',
            'icon' => 'package',
            'schema' => $schema,
        ]);
    }

    /** Download a PDF (SSRF-guarded, capped) and store it as a site asset. */
    private function importPdf(AssetService $assets, Site $site, string $url, string $name): ?Asset
    {
        try {
            SsrfGuard::assertPublicHttpUrl($url);
            $res = Http::timeout(30)->connectTimeout(10)->withUserAgent('Stillopress-Importer/1.0')->get($url);
            if (!$res->successful()) {
                return null;
            }
            $body = $res->body();
            if ($body === '' || strlen($body) > 30 * 1024 * 1024) {
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'vioiv_pdf_');
            file_put_contents($tmp, $body);
            try {
                $filename = (Str::slug($name) ?: 'catalog') . '.pdf';

                return $assets->upload($site, new UploadedFile($tmp, $filename, 'application/pdf', null, true));
            } finally {
                @unlink($tmp);
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cleanDescription(string $html): string
    {
        // Drop the injected PDF-download button block — we have a PDF field.
        $html = preg_replace('#<div class="vioiv-pdf-top".*?</div>#is', '', $html);

        return trim((string) $html);
    }

    private function decode(?string $s): string
    {
        return trim(html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @return array<int, string> distinct non-null values for a row key */
    private function distinct(array $rows, string $key): array
    {
        $vals = [];
        foreach ($rows as $r) {
            $v = $r[$key] ?? null;
            if (is_string($v) && $v !== '' && !in_array($v, $vals, true)) {
                $vals[] = $v;
            }
        }
        sort($vals);

        return $vals;
    }
}
