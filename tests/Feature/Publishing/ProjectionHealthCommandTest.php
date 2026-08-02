<?php

namespace Tests\Feature\Publishing;

use App\Models\Block;
use App\Models\Page;
use App\Models\SiteHealthReport;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProjectionHealthCommandTest extends TestCase
{
    public function test_reports_broken_internal_link(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);

        $a = Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'a']);
        Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'b']);

        Block::create([
            'blockable_type' => $a->getMorphClass(), 'blockable_id' => $a->id,
            'parent_block_id' => null, 'type' => 'rich-text', 'order' => 0,
            'data' => ['content' => '<p><a href="/b/">ok</a> and <a href="/gone/">dead</a></p>'],
        ]);

        $code = Artisan::call('projection:health', ['site' => $site->slug]);
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('BROKEN', $out);
        $this->assertStringContainsString('/gone/', $out);
        $this->assertStringNotContainsString('/b/', $out); // the valid link is not reported
    }

    public function test_strict_flag_exits_nonzero_on_broken(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $a = Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'a']);
        Block::create([
            'blockable_type' => $a->getMorphClass(), 'blockable_id' => $a->id,
            'parent_block_id' => null, 'type' => 'rich-text', 'order' => 0,
            'data' => ['content' => '<p><a href="/nowhere/">dead</a></p>'],
        ]);

        $code = Artisan::call('projection:health', ['site' => $site->slug, '--strict' => true]);
        $this->assertSame(1, $code);
    }

    public function test_store_persists_report_to_ledger(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $a = Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'a']);
        Block::create([
            'blockable_type' => $a->getMorphClass(), 'blockable_id' => $a->id,
            'parent_block_id' => null, 'type' => 'rich-text', 'order' => 0,
            'data' => ['content' => '<p><a href="/gone/">dead</a></p>'],
        ]);

        Artisan::call('projection:health', ['site' => $site->slug, '--store' => true]);

        $report = SiteHealthReport::where('site_id', $site->id)->where('type', 'broken_links')->first();
        $this->assertNotNull($report);
        $this->assertSame(1, $report->summary['broken_count']);
        $this->assertSame('/gone/', $report->data['broken'][0]['target']);
    }

    public function test_clean_site_reports_success(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        Page::factory()->create(['site_id' => $site->id, 'status' => 'published', 'slug' => 'a']);

        $code = Artisan::call('projection:health', ['site' => $site->slug]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('No broken internal links', Artisan::output());
    }
}
