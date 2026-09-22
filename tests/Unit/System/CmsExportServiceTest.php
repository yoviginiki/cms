<?php

namespace Tests\Unit\System;

use App\Domain\System\Services\CmsExportService;
use Tests\TestCase;

class CmsExportServiceTest extends TestCase
{
    public function test_export_contains_the_whole_source_tree_but_no_generated_or_secret_files(): void
    {
        $tmp = sys_get_temp_dir() . '/cms-export-test-' . uniqid() . '.zip';
        $r = app(CmsExportService::class)->build($tmp);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmp);
        @rmdir(dirname($tmp) . '/tmp');

        $this->assertSame($r['files'], count($names));
        // the source is all there
        foreach (['app/Http/Controllers/Api/V1/SystemController.php', 'routes/api.php', 'composer.json', 'artisan', 'phpunit.xml',
            'resources/admin/src/components/canvas/CanvasEditor.tsx', 'docs/GUIDE-CANVAS-EDITOR.md', 'tests/TestCase.php', 'public/index.php', '.env.example'] as $must) {
            $this->assertContains($must, $names, "missing {$must}");
        }
        // nothing generated or secret
        $this->assertNotContains('.env', $names);
        foreach ($names as $n) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)(vendor|node_modules|\.git|storage)/#', $n, $n);
            $this->assertNotSame('.phpunit.result.cache', basename($n));
        }
        // only the current admin bundle's chunks, never stale ones
        $manifestPath = base_path('public/admin-assets/.vite/manifest.json');
        if (is_file($manifestPath)) {
            $this->assertContains('public/admin-assets/.vite/manifest.json', $names);
            $keep = [];
            foreach (json_decode(file_get_contents($manifestPath), true) as $e) {
                foreach (array_merge([$e['file']], $e['css'] ?? [], $e['assets'] ?? []) as $f) {
                    $keep['public/admin-assets/' . $f] = true;
                }
            }
            $chunks = array_filter($names, fn ($n) => str_starts_with($n, 'public/admin-assets/assets/'));
            $this->assertNotEmpty($chunks);
            foreach ($chunks as $c) {
                $this->assertArrayHasKey($c, $keep, "stale chunk exported: {$c}");
            }
        }
    }
}
