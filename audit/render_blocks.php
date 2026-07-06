<?php
/**
 * AUDIT (read-only) — Session B #5: render every registered block view with
 * (a) empty data and (b) a generic populated fixture. Catch throws + empty output.
 * Does NOT modify anything. Run: php audit/render_blocks.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Blocks\Services\BlockRegistry;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

// Tenant context so Site loads under RLS
DB::unprepared("SET app.current_tenant_id = '019dfba5-a96b-719d-954d-60a4a549f949'");
$site = Site::first();
if (!$site) { fwrite(STDERR, "no site\n"); exit(1); }

$registry = app(BlockRegistry::class);
$types = array_column($registry->getAllTypes(), 'type');
sort($types);

// A generic fixture covering keys many blocks read.
$fixture = [
    'title' => 'Title', 'heading' => 'Heading', 'text' => 'Text body', 'content' => '<p>Content</p>',
    'subtitle' => 'Sub', 'label' => 'Label', 'caption' => 'Cap', 'quote' => 'Q', 'author' => 'A',
    'url' => 'https://example.com', 'href' => 'https://example.com', 'src' => 'https://example.com/x.jpg',
    'image' => 'https://example.com/x.jpg', 'alt' => 'alt', 'level' => 'h2', 'text_align' => 'left',
    'items' => [['title' => 'I1', 'content' => '<p>c</p>', 'text' => 't', 'url' => '#', 'label' => 'l']],
    'columns' => [['content' => 'c']], 'slides' => [['image' => 'x.jpg']], 'rows' => [['cells' => ['a']]],
    'links' => [['label' => 'L', 'url' => '#']], 'stats' => [['value' => '1', 'label' => 'x']],
    'images' => ['a.jpg', 'b.jpg'], 'options' => ['a', 'b'], 'tabs' => [['title' => 'T', 'content' => 'c']],
    'value' => '10', 'min' => 0, 'max' => 100, 'percentage' => 50, 'code' => 'x=1', 'language' => 'js',
    'html' => '<p>e</p>', 'embed' => '<p>e</p>', 'icon' => 'star', 'color' => '#333', 'size' => 'md',
];

$ctx = fn($data) => [
    'data' => $data, 'children' => '', 'childrenArray' => [], 'site' => $site,
    'blockStyle' => [], 'blockAnimation' => [], 'blockAdvanced' => [], 'blockResponsive' => [],
];

$results = [];
foreach ($types as $t) {
    $view = "blocks.{$t}";
    $row = ['type' => $t, 'view' => View::exists($view)];
    if (!$row['view']) { $results[] = $row + ['empty' => 'NO_VIEW', 'fixture' => 'NO_VIEW']; continue; }
    foreach (['empty' => [], 'fixture' => $fixture] as $mode => $data) {
        try {
            $html = View::make($view, $ctx($data))->render();
            $len = strlen(trim($html));
            $row[$mode] = $len === 0 ? 'EMPTY(0)' : "ok({$len})";
        } catch (\Throwable $e) {
            $row[$mode] = 'THROW: ' . str_replace("\n", ' ', substr($e->getMessage(), 0, 160));
        }
    }
    $results[] = $row;
}

$throwsEmpty = 0; $throwsFixture = 0; $emptyEmpty = 0; $noView = 0;
foreach ($results as $r) {
    if (($r['empty'] ?? '') === 'NO_VIEW') { $noView++; }
    if (str_starts_with($r['empty'] ?? '', 'THROW')) $throwsEmpty++;
    if (str_starts_with($r['fixture'] ?? '', 'THROW')) $throwsFixture++;
    if (($r['empty'] ?? '') === 'EMPTY(0)') $emptyEmpty++;
    printf("%-22s | empty:%-40s | fixture:%s\n", $r['type'], $r['empty'] ?? '-', $r['fixture'] ?? '-');
}
printf("\nTOTAL types=%d  no_view=%d  THROW_on_empty=%d  THROW_on_fixture=%d  EMPTY_on_empty=%d\n",
    count($results), $noView, $throwsEmpty, $throwsFixture, $emptyEmpty);
