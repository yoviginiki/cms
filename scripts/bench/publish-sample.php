<?php
// Publishes a representative sample site from THIS checkout into a sandbox
// docroot for Lighthouse (H04). Test DB only.
if (getenv('APP_ENV') !== 'testing' || in_array(getenv('DB_DATABASE'), [false, '', 'cms_saas_platform'], true)) { fwrite(STDERR, "APP_ENV=testing and a non-production DB_DATABASE required\n"); exit(1); }
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$root = $argv[1] ?? storage_path('framework/testing/lh');
config(['publishing.public_path' => "{$root}/public", 'publishing.staging_path' => "{$root}/builds", 'queue.default' => 'sync', 'publishing.deploy_strategy' => 'symlink']);
$tenant = App\Models\Tenant::factory()->create();
$owner = App\Models\User::factory()->owner()->create(['tenant_id' => $tenant->id]);
DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
$site = app(App\Domain\Sites\Services\SiteService::class)->createSite(['name' => 'Lighthouse Sample', 'slug' => 'lh-sample-' . substr(md5(uniqid()), 0, 6)], $tenant);
$page = App\Models\Page::create(['site_id' => $site->id, 'title' => 'Home', 'slug' => 'home', 'status' => 'published', 'seo_meta' => ['description' => 'A representative sample page for measuring published output.']]);
$site->update(['settings' => ['homepage_id' => $page->id, 'homepage_type' => 'page']]);
$sec = fn (array $modules, int $o) => ['type' => 'section', 'level' => 'section', 'order' => $o, 'data' => [], 'children' => [['type' => 'row', 'level' => 'row', 'order' => 0, 'data' => ['layout' => '1'], 'children' => [['type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [], 'children' => $modules]]]]];
$tree = [
    $sec([['type' => 'hero', 'level' => 'module', 'order' => 0, 'data' => ['title' => 'Measured, not estimated', 'subtitle' => 'Published output of the stabilization branch', 'bg_type' => 'color', 'bg_color' => '#1a1a2e']]], 0),
    $sec([['type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => ['text' => 'Section heading', 'level' => 'h2']],
          ['type' => 'text', 'level' => 'module', 'order' => 1, 'data' => ['content' => '<p>' . str_repeat('Readable body copy for a realistic paragraph. ', 30) . '</p>']],
          ['type' => 'button', 'level' => 'module', 'order' => 2, 'data' => ['text' => 'Contact us', 'url' => '/contact/']]], 1),
    $sec([['type' => 'accordion', 'level' => 'module', 'order' => 0, 'data' => ['items' => [['title' => 'Question one', 'content' => '<p>Answer one.</p>'], ['title' => 'Question two', 'content' => '<p>Answer two.</p>']]]]], 2),
];
app(App\Domain\Blocks\Services\BlockService::class)->syncTrusted($page, $tree);
$d = app(App\Domain\Publishing\Services\PublishOrchestrator::class)->publish($site->fresh(), $owner, 'full');
echo "status={$d->fresh()->status} docroot={$root}/public/{$site->slug}\n";
