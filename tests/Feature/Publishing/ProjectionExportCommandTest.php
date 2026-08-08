<?php

namespace Tests\Feature\Publishing;

use App\Models\Block;
use App\Models\Page;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Export consumer — CLI delivery. Read-only render of a page projection.
 */
class ProjectionExportCommandTest extends TestCase
{
    private function siteWithPage(): array
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'about']);

        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'Export Heading Test', 'level' => 'h2'],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'image', 'order' => 1,
            'data' => ['url' => 'https://cdn/z.jpg', 'alt' => 'Zeta'],
        ]);

        return [$site->fresh(), $page->fresh()];
    }

    public function test_exports_markdown(): void
    {
        [$site, $page] = $this->siteWithPage();

        $code = Artisan::call('projection:export', [
            'site' => $site->slug,
            '--page' => $page->slug,
            '--format' => 'md',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('## Export Heading Test', $out);
        $this->assertStringContainsString('![Zeta](https://cdn/z.jpg)', $out);
    }

    public function test_exports_json(): void
    {
        [$site, $page] = $this->siteWithPage();

        $code = Artisan::call('projection:export', [
            'site' => $site->slug,
            '--page' => $page->slug,
            '--format' => 'json',
        ]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(0, $code);
        $this->assertSame('1.0', $decoded['projection_version']);
        $this->assertNotEmpty($decoded['schema_org']['@graph']);
    }

    public function test_missing_content_selector_fails(): void
    {
        [$site] = $this->siteWithPage();

        $code = Artisan::call('projection:export', ['site' => $site->slug, '--format' => 'json']);
        $this->assertSame(1, $code);
    }
}
