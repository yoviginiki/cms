<?php

namespace Tests\Unit\System;

use App\Domain\System\Services\UpdateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F02 — UpdateService::applyUpdate() must refuse a package that would write
 * outside the allow-listed application paths, must not follow symlink
 * entries, and must roll the files back when the migration step fails.
 * Everything runs against a sandbox base path — never the real checkout.
 */
class UpdateServiceHardeningTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = storage_path('framework/testing/update-' . uniqid());
        File::ensureDirectoryExists("{$this->sandbox}/base/app");
        File::put("{$this->sandbox}/base/app/Existing.php", 'v1');
        File::put("{$this->sandbox}/base/.env", 'SECRET=1');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);
        parent::tearDown();
    }

    private function service(?\Closure $migrator = null): UpdateService
    {
        return new UpdateService(
            basePath: "{$this->sandbox}/base",
            updateDir: "{$this->sandbox}/updates",
            migrator: $migrator ?? fn () => null,
        );
    }

    private function zip(array $entries, array $symlinks = []): string
    {
        $path = "{$this->sandbox}/pkg-" . uniqid() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        foreach ($symlinks as $name => $target) {
            $zip->addFromString($name, $target);
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }
        $zip->close();
        return $path;
    }

    public function test_traversal_entries_reject_the_whole_package_before_any_change(): void
    {
        $zip = $this->zip(['app/Existing.php' => 'v2', '../outside.txt' => 'escape']);
        try {
            $this->service()->applyUpdate($zip);
            $this->fail('expected rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('outside.txt', $e->getMessage());
        }
        $this->assertStringEqualsFile("{$this->sandbox}/base/app/Existing.php", 'v1');
        $this->assertFileDoesNotExist("{$this->sandbox}/outside.txt");
    }

    public function test_entries_outside_the_allowed_paths_reject_the_package(): void
    {
        foreach ([['.env' => 'PWNED=1'], ['storage/logs/x' => 'x'], ['public/admin-assets/index.html' => 'x'], ['bin/run.sh' => 'x']] as $entries) {
            $zip = $this->zip(array_merge(['app/Existing.php' => 'v2'], $entries));
            try {
                $this->service()->applyUpdate($zip);
                $this->fail('expected rejection for ' . array_key_first($entries));
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString(array_key_first($entries), $e->getMessage());
            }
        }
        $this->assertStringEqualsFile("{$this->sandbox}/base/app/Existing.php", 'v1');
        $this->assertStringEqualsFile("{$this->sandbox}/base/.env", 'SECRET=1');
    }

    public function test_symlink_entries_are_refused(): void
    {
        $zip = $this->zip(['app/Existing.php' => 'v2'], ['app/link.php' => '/etc/passwd']);
        $this->expectException(\RuntimeException::class);
        $this->service()->applyUpdate($zip);
    }

    public function test_migration_failure_restores_files_and_reports_failure(): void
    {
        $zip = $this->zip(['app/Existing.php' => 'v2', 'app/New.php' => 'new']);
        try {
            $this->service(fn () => throw new \RuntimeException('boom'))->applyUpdate($zip);
            $this->fail('expected the migration failure to surface');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }
        $this->assertStringEqualsFile("{$this->sandbox}/base/app/Existing.php", 'v1');
        $this->assertFileDoesNotExist("{$this->sandbox}/base/app/New.php");
    }

    public function test_a_valid_package_is_applied(): void
    {
        $zip = $this->zip(['app/Existing.php' => 'v2', 'app/New.php' => 'new', 'config/x.php' => 'cfg']);
        $result = $this->service()->applyUpdate($zip);

        $this->assertSame(3, $result['files_updated']);
        $this->assertStringEqualsFile("{$this->sandbox}/base/app/Existing.php", 'v2');
        $this->assertStringEqualsFile("{$this->sandbox}/base/app/New.php", 'new');
        $this->assertStringEqualsFile("{$this->sandbox}/base/.env", 'SECRET=1');
    }
}
