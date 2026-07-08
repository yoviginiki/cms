<?php

/**
 * Two-client collab harness — fixture seeder.
 * Run with the harness env (see run.sh): DB_DATABASE=cms_saas_platform_test.
 * Assumes the schema is already migrated (run.sh runs plain `migrate` first).
 * Seeds a fresh tenant, two owner users, a site and a canvas page, then writes
 * collab-harness/fixture.json for the spec. Idempotent-ish: each run creates a
 * new isolated tenant, so old fixtures are simply ignored.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

fwrite(STDOUT, 'DB: '.config('database.connections.'.config('database.default').'.database')."\n");

$tenant = Tenant::factory()->create();
DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");

$run = substr(md5(uniqid('', true)), 0, 6);
$aliceEmail = "alice+{$run}@harness.test";
$bobEmail = "bob+{$run}@harness.test";
$alice = User::factory()->owner()->create([
    'tenant_id' => $tenant->id, 'name' => 'Alice', 'email' => $aliceEmail, 'password' => Hash::make('password'),
]);
$bob = User::factory()->owner()->create([
    'tenant_id' => $tenant->id, 'name' => 'Bob', 'email' => $bobEmail, 'password' => Hash::make('password'),
]);

$site = Site::factory()->create(['tenant_id' => $tenant->id]);
$page = Page::factory()->create([
    'site_id' => $site->id, 'title' => 'Collab', 'slug' => 'collab',
    'status' => 'draft', 'editor_mode' => 'canvas',
    'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
]);
Block::create([
    'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
    'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
    'data' => ['canvas' => ['height' => 500, 'bleed' => false]],
]);

file_put_contents(__DIR__.'/fixture.json', json_encode([
    'appOrigin' => 'http://127.0.0.1:8000',
    'reverb' => ['key' => 'smokekey', 'host' => '127.0.0.1', 'port' => 9099],
    'siteId' => $site->id,
    'pageId' => $page->id,
    'users' => [
        ['email' => $aliceEmail, 'password' => 'password', 'id' => (string) $alice->id, 'name' => 'Alice'],
        ['email' => $bobEmail, 'password' => 'password', 'id' => (string) $bob->id, 'name' => 'Bob'],
    ],
], JSON_PRETTY_PRINT));

fwrite(STDOUT, "seeded canvas page {$page->id} (site {$site->id}); 2 users\n");
