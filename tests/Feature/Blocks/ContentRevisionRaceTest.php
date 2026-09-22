<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Exceptions\StaleContentRevisionException;
use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * F13 — the real race: two OS processes, two independent DB connections,
 * both saving from the same revision at the same moment. Exactly one wins;
 * the other gets StaleContentRevisionException; the stored tree is the
 * winner's, never a mix. Uses committed data (DatabaseTruncation) because
 * a wrapping test transaction would be invisible to the other connections.
 */
class ContentRevisionRaceTest extends BaseTestCase
{
    use DatabaseTruncation;

    /**
     * The trait's "table has rows?" probe runs without a tenant GUC, which the
     * RLS policies reject; migrations are still ensured by the trait, rows
     * are removed explicitly at the end of the test instead.
     */
    protected function connectionsToTruncate(): array
    {
        return [];
    }

    public function test_two_concurrent_saves_from_the_same_revision_yield_one_winner(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl not available');
        }

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
        $site = Site::factory()->create(['tenant_id' => $tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id]);
        app(BlockService::class)->syncBlocks($page, [['type' => 'text', 'order' => 0, 'data' => ['content' => 'base']]]);
        $startRevision = app(BlockService::class)->blocksVersion($page);

        $dir = storage_path('framework/testing/race-' . uniqid());
        File::ensureDirectoryExists($dir);
        $go = "{$dir}/go";

        $children = [];
        foreach (['A', 'B'] as $tag) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                // CHILD: keep the inherited PDO alive (never let it be destructed
                // on the shared socket), open a private connection, wait for GO.
                $GLOBALS['__inherited_pdo'] = DB::connection()->getPdo();
                DB::purge();
                DB::reconnect();
                DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
                $svc = app(BlockService::class);
                $tree = [
                    ['type' => 'text', 'order' => 0, 'data' => ['content' => "from {$tag} 1"]],
                    ['type' => 'text', 'order' => 1, 'data' => ['content' => "from {$tag} 2"]],
                ];
                while (!file_exists($go)) {
                    usleep(1000);
                }
                try {
                    $svc->syncBlocks($page, $tree, $startRevision);
                    file_put_contents("{$dir}/{$tag}", 'ok');
                } catch (StaleContentRevisionException) {
                    file_put_contents("{$dir}/{$tag}", 'stale');
                } catch (\Throwable $e) {
                    file_put_contents("{$dir}/{$tag}", 'error: ' . $e->getMessage());
                }
                pcntl_exec('/bin/true');
                exit(1);
            }
            $children[] = $pid;
        }

        usleep(50_000);
        touch($go);
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [file_get_contents("{$dir}/A"), file_get_contents("{$dir}/B")];
        sort($results);
        $this->assertSame(['ok', 'stale'], $results, 'expected exactly one winner, got: ' . json_encode($results));

        // Stored tree belongs to the winner only — both rows from the same process.
        // (The parent's own connection is intact: the children exec'd away
        // without ever closing the inherited socket.)
        $contents = Block::where('blockable_id', $page->id)->orderBy('order')->pluck('data')->map(fn ($d) => $d['content'])->all();
        $this->assertCount(2, $contents);
        $this->assertSame(1, count(array_unique(array_map(fn ($c) => substr($c, 0, 6), $contents))), 'mixed tree: ' . json_encode($contents));
        $this->assertSame((string) ((int) $startRevision + 1), app(BlockService::class)->blocksVersion($page->fresh()));

        File::deleteDirectory($dir);
        Block::where('blockable_id', $page->id)->delete();
        $page->forceDelete();
        $site->forceDelete();
        $owner->forceDelete();
        $tenant->forceDelete();
    }
}
