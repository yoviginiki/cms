<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\BuildRetention;
use App\Domain\Publishing\Services\DeploymentGate;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F18 (audit 2026-09-22) — release artifacts are immutable: a delta onto a
 * symlink-served site produces a NEW release (hard-link copy + merge + swap)
 * and never modifies the previous build; rollback returns exactly that
 * build; retention keeps active/staged/previous/live builds.
 */
class ImmutableReleaseTest extends TestCase
{
    private function treeHash(string $dir): string
    {
        $items = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $items[] = substr($f->getPathname(), strlen($dir)) . ':' . md5_file($f->getPathname());
        }
        sort($items);
        return md5(implode("\n", $items));
    }

    public function test_delta_creates_a_new_release_and_rollback_returns_the_old_one_byte_for_byte(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(2);
        $pages = Page::where('site_id', $site->id)->orderBy('sort_order')->get();
        $site->update(['settings' => ['homepage_id' => $pages[0]->id]]);
        $site = $site->fresh();
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        // Full A
        $pages[1]->update(['title' => 'VERSION A']);
        $a = app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $aDir = readlink($docroot);
        $aHash = $this->treeHash($aDir);
        $this->assertStringContainsString('VERSION A', file_get_contents("{$docroot}/{$pages[1]->slug}/index.html"));

        // Delta B (only page 1 changed)
        $pages[1]->update(['title' => 'VERSION B']);
        $b = app(DeploymentGate::class)->open($site, 'stale_batch', $this->owner, [
            'targets' => ['pages' => [$pages[1]->id], 'posts' => [], 'records' => []], 'auto_promote' => true,
        ], fn (Deployment $d) => RepublishStaleJob::dispatchSync($d));
        $this->assertSame('live', $b->fresh()->status, json_encode($b->fresh()->metadata) . ' ' . $b->fresh()->error_log);

        $bDir = readlink($docroot);
        $this->assertNotSame($aDir, $bDir, 'delta must produce a new release, not write into A');
        $this->assertStringEndsWith("{$b->id}-release", $bDir);
        $this->assertStringContainsString('VERSION B', file_get_contents("{$docroot}/{$pages[1]->slug}/index.html"));
        $this->assertFileExists("{$docroot}/index.html"); // the untouched homepage carried over
        $this->assertSame($aHash, $this->treeHash($aDir), 'release A was mutated by the delta');
        $this->assertSame($aDir, $b->fresh()->metadata['previous_build']);

        // Rollback to A returns exactly A
        $rb = app(PublishOrchestrator::class)->rollback($site->fresh(), $a->fresh(), $this->owner);
        $this->assertSame('rolled_back', $rb->fresh()->status);
        $this->assertSame(realpath($aDir), realpath(readlink($docroot)));
        $this->assertSame($aHash, $this->treeHash(readlink($docroot)));
        $this->assertStringContainsString('VERSION A', file_get_contents("{$docroot}/{$pages[1]->slug}/index.html"));

        // Rollback to the delta deployment B returns the full B release (never its partial staging dir)
        $rb2 = app(PublishOrchestrator::class)->rollback($site->fresh(), $b->fresh(), $this->owner);
        $this->assertSame('rolled_back', $rb2->fresh()->status);
        $this->assertSame(realpath($bDir), realpath(readlink($docroot)));
        $this->assertFileExists("{$docroot}/index.html");
    }

    public function test_retention_keeps_live_previous_staged_and_active_builds_across_sites(): void
    {
        $root = storage_path('framework/testing/retention2-' . uniqid());
        File::ensureDirectoryExists("{$root}/builds");
        File::ensureDirectoryExists("{$root}/public");
        config(['publishing.staging_path' => "{$root}/builds", 'publishing.public_path' => "{$root}/public"]);
        $this->setTenantScope($this->owner);
        $siteA = Site::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'ret-a']);
        $siteB = Site::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'ret-b']);

        $mk = function (Site $site, string $status, array $meta = [], int $age = 0) use ($root) {
            $d = Deployment::create(['site_id' => $site->id, 'type' => 'full', 'status' => $status, 'triggered_by' => $this->owner->id, 'metadata' => $meta, 'completed_at' => in_array($status, ['live', 'rolled_back']) ? now()->subMinutes($age) : null]);
            $dir = "{$root}/builds/{$d->id}";
            File::ensureDirectoryExists($dir);
            File::put("{$dir}/index.html", $d->id);
            touch($dir, time() - 86400 * 30 + ($age > 0 ? -$age : 0)); // all "old"
            return [$d, $dir];
        };

        [$aOld, $aOldDir] = $mk($siteA, 'live', [], 60);           // A's previous live (rollback target of aLive)
        [$aLive, $aLiveDir] = $mk($siteA, 'live', ['previous_build' => $aOldDir], 1);
        symlink($aLiveDir, "{$root}/public/ret-a");
        [$aStaged, $aStagedDir] = $mk($siteA, 'staged');
        [$aOlder, $aOlderDir] = $mk($siteA, 'live', [], 200);      // genuinely old, unreferenced
        [$bActive, $bActiveDir] = $mk($siteB, 'building');
        [$bOld, $bOldDir] = $mk($siteB, 'failed', [], 300);
        File::ensureDirectoryExists("{$root}/builds/not-a-uuid-legacy");
        touch("{$root}/builds/not-a-uuid-legacy", time() - 86400 * 40);

        // Many "other site" publishes would previously push these out of the global top-N.
        BuildRetention::prune(0);

        $this->assertDirectoryExists($aLiveDir, 'live build pruned');
        $this->assertDirectoryExists($aOldDir, 'rollback target (previous_build) pruned');
        $this->assertDirectoryExists($aStagedDir, 'staged batch pruned');
        $this->assertDirectoryExists($bActiveDir, 'active build pruned');
        $this->assertDirectoryDoesNotExist($aOlderDir);
        $this->assertDirectoryDoesNotExist($bOldDir);

        File::deleteDirectory($root);
    }
}
