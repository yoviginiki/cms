<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

/**
 * F15 — two OS processes request a publish for the same site at the same
 * moment: exactly one deployment is created. Committed data (no wrapping
 * transaction) so both processes see the site.
 */
class DeploymentGateRaceTest extends BaseTestCase
{
    use DatabaseTruncation;

    protected function connectionsToTruncate(): array
    {
        return []; // RLS hides rows from the trait's probe; rows are removed explicitly below
    }

    public function test_two_concurrent_publish_requests_create_exactly_one_deployment(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl not available');
        }
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner()->create(['tenant_id' => $tenant->id]);
        DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
        $site = Site::factory()->create(['tenant_id' => $tenant->id]);

        $dir = storage_path('framework/testing/gate-race-' . uniqid());
        File::ensureDirectoryExists($dir);
        $go = "{$dir}/go";
        $children = [];
        foreach (['A', 'B'] as $tag) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                $GLOBALS['__inherited_pdo'] = DB::connection()->getPdo();
                DB::purge();
                DB::reconnect();
                DB::unprepared("SET app.current_tenant_id = '{$tenant->id}'");
                config(['queue.default' => 'redis']); // dispatch (faked) instead of running inline
                Queue::fake();
                while (!file_exists($go)) {
                    usleep(1000);
                }
                try {
                    app(PublishOrchestrator::class)->publish($site, $owner, 'full');
                    file_put_contents("{$dir}/{$tag}", 'ok');
                } catch (\RuntimeException $e) {
                    file_put_contents("{$dir}/{$tag}", 'refused');
                } catch (\Throwable $e) {
                    file_put_contents("{$dir}/{$tag}", 'error: ' . get_class($e) . ' ' . $e->getMessage());
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
        $this->assertSame(['ok', 'refused'], $results, json_encode($results));
        $this->assertSame(1, Deployment::where('site_id', $site->id)->count());

        File::deleteDirectory($dir);
        Deployment::where('site_id', $site->id)->delete();
        $site->forceDelete();
        $owner->forceDelete();
        $tenant->forceDelete();
    }
}
