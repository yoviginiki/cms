<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildRetention;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * FIX-B6a — pruning must never delete a build a live site symlink points to.
 */
class BuildRetentionTest extends TestCase
{
    public function test_prune_never_deletes_a_live_symlink_target(): void
    {
        $root = storage_path('framework/testing/retention-' . uniqid());
        $builds = "{$root}/builds";
        $public = "{$root}/public";
        File::ensureDirectoryExists($builds);
        File::ensureDirectoryExists($public);
        config(['publishing.staging_path' => $builds, 'publishing.public_path' => $public]);

        // 5 build dirs; the OLDEST is the one a live site serves.
        $dirs = [];
        foreach (range(1, 5) as $i) {
            $d = "{$builds}/dep{$i}";
            File::ensureDirectoryExists($d);
            File::put("{$d}/index.html", "build {$i}");
            touch($d, time() + $i); // dep5 newest, dep1 oldest
            $dirs[$i] = $d;
        }

        // Live site symlink -> the OLDEST build (dep1)
        symlink($dirs[1], "{$public}/mysite");

        // Keep only the 2 newest — normally this would delete dep1..dep3
        BuildRetention::prune(2);

        // dep1 is old BUT live -> must survive; dep2/dep3 (old, not live) -> gone
        $this->assertDirectoryExists($dirs[1], 'live build was pruned');
        $this->assertDirectoryDoesNotExist($dirs[2]);
        $this->assertDirectoryDoesNotExist($dirs[3]);
        $this->assertDirectoryExists($dirs[4]);
        $this->assertDirectoryExists($dirs[5]);

        File::deleteDirectory($root);
    }
}
