<?php
// Run: sudo -u cytechno env APP_ENV=testing DB_DATABASE=<TEST DB> CACHE_STORE=array QUEUE_CONNECTION=sync REDIS_ENABLED=false php scripts/bench/blocks-bench.php
// NEVER against the production database (it creates tenants/sites).
if (getenv('APP_ENV') !== 'testing' || in_array(getenv('DB_DATABASE'), [false, '', 'cms_saas_platform'], true)) { fwrite(STDERR, "APP_ENV=testing and a non-production DB_DATABASE required\n"); exit(1); }
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['publishing.public_path' => storage_path('framework/testing/bench-pub'), 'publishing.staging_path' => storage_path('framework/testing/bench-builds'), 'queue.default' => 'sync']);
$tenant = App\Models\Tenant::factory()->create();
$owner = App\Models\User::factory()->owner()->create(['tenant_id' => $tenant->id]);
DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
$site = App\Models\Site::factory()->create(['tenant_id' => $tenant->id]);
$svc = app(App\Domain\Blocks\Services\BlockService::class);
function tree(int $modules): array {
    $cols = [];
    for ($i = 0; $i < $modules; $i++) $cols[] = ['type' => 'text', 'level' => 'module', 'order' => $i, 'data' => ['content' => "<p>Block {$i} ".str_repeat('lorem ', 20)."</p>"]];
    $sections = [];
    foreach (array_chunk($cols, 20) as $k => $chunk) {
        $sections[] = ['type' => 'section', 'level' => 'section', 'order' => $k, 'data' => [], 'children' => [['type' => 'row', 'level' => 'row', 'order' => 0, 'data' => [], 'children' => [['type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [], 'children' => $chunk]]]]];
    }
    return $sections;
}
printf("%-8s %-10s %-9s %-9s %-10s %-10s\n", 'modules', 'nodes', 'save_ms', 'queries', 'mem_MB', 'build_ms');
foreach ([50, 200, 500] as $n) {
    $page = App\Models\Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
    $t = tree($n);
    $nodes = $n + 3 * count($t);
    $svc->syncBlocks($page, $t); // warm + initial content
    DB::flushQueryLog(); DB::enableQueryLog();
    gc_collect_cycles(); $m0 = memory_get_usage(true); $t0 = hrtime(true);
    $svc->syncBlocks($page, $t, null);
    $ms = (hrtime(true) - $t0) / 1e6; $q = count(DB::getQueryLog()); DB::disableQueryLog();
    $mem = (memory_get_peak_usage(true)) / 1048576;
    $t1 = hrtime(true);
    app(App\Domain\Publishing\Services\BuildPageService::class)->build($page->fresh(), $site->theme, $site);
    $bms = (hrtime(true) - $t1) / 1e6;
    printf("%-8d %-10d %-9.0f %-9d %-10.1f %-10.0f\n", $n, $nodes, $ms, $q, $mem, $bms);
}
// full publish of a site with many posts
foreach ([100] as $posts) {
    $s2 = App\Models\Site::factory()->create(['tenant_id' => $tenant->id]);
    $home = App\Models\Page::factory()->published()->create(['site_id' => $s2->id]);
    $s2->update(['settings' => ['homepage_id' => $home->id]]);
    for ($i = 0; $i < $posts; $i++) {
        $p = App\Models\Post::factory()->published()->create(['site_id' => $s2->id]);
        $svc->syncTrusted($p, [['type' => 'text', 'order' => 0, 'data' => ['content' => '<p>'.str_repeat('text ', 200).'</p>']]]);
    }
    gc_collect_cycles(); $t0 = hrtime(true);
    $d = app(App\Domain\Publishing\Services\PublishOrchestrator::class)->publish($s2->fresh(), $owner, 'full');
    printf("full publish %d posts: %.1fs, status %s, peak %.0f MB\n", $posts, (hrtime(true)-$t0)/1e9, $d->fresh()->status, memory_get_peak_usage(true)/1048576);
}
// ── split: validation vs write for 500 ──
$t = tree(500);
$v = new App\Domain\Blocks\Support\BlockTreeValidator(app(App\Domain\Blocks\Services\BlockRegistry::class));
$t0 = hrtime(true); $v->validate($t); printf("validate 500: %.0f ms\n", (hrtime(true)-$t0)/1e6);
$page = App\Models\Page::factory()->create(['site_id' => $site->id]);
$svc->syncTrusted($page, $t);
$t0 = hrtime(true); $svc->syncTrusted($page, $t); printf("sync trusted (no field rules) 500: %.0f ms\n", (hrtime(true)-$t0)/1e6);
$t0 = hrtime(true); $svc->getBlockTree($page); printf("getBlockTree 500: %.0f ms\n", (hrtime(true)-$t0)/1e6);
$t0 = hrtime(true); app(App\Domain\References\Services\ReferenceRecorder::class)->recompute($page); printf("references recompute 500: %.0f ms\n", (hrtime(true)-$t0)/1e6);
DB::flushQueryLog(); DB::enableQueryLog(); $t0 = hrtime(true);
app(App\Domain\Publishing\Services\BuildPageService::class)->build($page->fresh(), $site->theme, $site);
$log = DB::getQueryLog(); DB::disableQueryLog();
printf("build 500: %.0f ms, %d queries\n", (hrtime(true)-$t0)/1e6, count($log));
$top = []; foreach ($log as $q) { $k = preg_replace('/\s+/', ' ', substr($q['query'], 0, 90)); $top[$k] = ($top[$k] ?? 0) + 1; } arsort($top);
foreach (array_slice($top, 0, 5, true) as $k => $c) printf("  %5d  %s\n", $c, $k);
