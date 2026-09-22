<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\OutputValidator;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Models\Deployment;
use App\Models\Page;
use Mockery;
use Tests\TestCase;

/**
 * F27 (audit 2026-09-22) — hard integrity errors (empty/truncated output)
 * block the release before the live switch; warnings never block; the score
 * is labelled heuristic.
 */
class OutputHardErrorTest extends TestCase
{
    public function test_validator_distinguishes_hard_errors_from_warnings(): void
    {
        $this->setTenantScope($this->owner);
        $site = \App\Models\Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id]);
        $v = app(OutputValidator::class);

        $ok = $v->validate('<!doctype html><html><head><title>x</title></head><body><img src="a.jpg"></body></html>', $page, $site);
        $this->assertTrue($ok['passed']);
        $this->assertSame([], $ok['errors']);
        $this->assertNotEmpty($ok['warnings']); // missing width/height → warning only

        foreach (['', '<html><body>cut off', '<body>no html element</body></html>'] as $bad) {
            $r = $v->validate($bad, $page, $site);
            $this->assertFalse($r['passed'], $bad);
            $this->assertNotEmpty($r['errors']);
        }
    }

    public function test_a_hard_render_failure_never_changes_the_live_site(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => ['homepage_id' => $page->id]]);
        $site = $site->fresh();
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $liveHash = md5_file("{$docroot}/index.html");
        $liveTarget = readlink($docroot);

        // The renderer now emits truncated output for the page.
        $broken = Mockery::mock(BuildPageService::class);
        $broken->shouldReceive('buildAndValidate')->andReturnUsing(function ($content, $theme, $s) {
            $html = '<!doctype html><html><body>truncated';

            return ['html' => $html, 'validation' => app(OutputValidator::class)->validate($html, $content, $s)];
        });
        $dep = Deployment::create(['site_id' => $site->id, 'type' => 'full', 'status' => 'queued', 'triggered_by' => $this->owner->id, 'metadata' => ['generation' => 2]]);
        $job = new PublishSiteJob($dep, 'full');
        try {
            $job->handle($broken, app(DeployService::class), app(SitemapGenerator::class), app(RobotsGenerator::class));
        } catch (\Throwable) {
        }

        $this->assertSame('failed', $dep->fresh()->status);
        $this->assertStringContainsString('invalid output', strtolower($dep->fresh()->error_log));
        $this->assertSame($liveTarget, readlink($docroot), 'live symlink must not move');
        $this->assertSame($liveHash, md5_file("{$docroot}/index.html"));
    }

    public function test_deployment_metadata_labels_the_score_as_heuristic(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => ['homepage_id' => $page->id]]);
        $dep = app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
        $this->assertSame('heuristic', $dep->fresh()->metadata['lighthouse_checks']['kind']);
    }
}
