<?php

namespace Tests\Feature\Api;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;
use App\Domain\Publishing\Services\MagazineStaticPublisher;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MagazineStaticPublishTest extends TestCase
{
    private Site $site;
    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->staging = storage_path('app/test-staging-' . uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->staging);
        parent::tearDown();
    }

    private function makeIssue(string $status): MagazineIssue
    {
        $issue = MagazineIssue::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'title' => 'The Static Quarterly',
            'status' => $status,
            'created_by' => $this->owner->id,
        ]);
        $spread = MagazineSpread::create(['issue_id' => $issue->id, 'spread_index' => 0]);
        $page = MagazineDtpPage::create([
            'issue_id' => $issue->id, 'spread_id' => $spread->id,
            'page_index' => 0, 'side' => 'single', 'width' => 595, 'height' => 842,
        ]);
        MagazineFrame::create([
            'issue_id' => $issue->id, 'page_id' => $page->id,
            'frame_type' => 'text', 'x' => 36, 'y' => 36, 'width' => 400, 'height' => 200,
            'z_index' => 1, 'content' => ['html' => '<p>STATIC MAGAZINE BODY</p>'],
        ]);

        return $issue;
    }

    public function test_published_issue_becomes_static_viewer(): void
    {
        $issue = $this->makeIssue('published');
        $svc = app(MagazineStaticPublisher::class);
        $built = $svc->publishForSite($this->site, $this->staging);

        $this->assertSame(1, $built);
        $file = "{$this->staging}/{$svc->issuePath($issue)}/index.html";
        $this->assertFileExists($file);
        $html = file_get_contents($file);
        $this->assertStringContainsString('sv-ctl', $html);               // viewer runtime
        $this->assertStringContainsString('STATIC MAGAZINE BODY', $html); // content
        $this->assertStringContainsString('The Static Quarterly', $html);
    }

    public function test_dom_order_is_reading_order_while_zindex_stacks(): void
    {
        // a frame at the TOP of the page with the HIGHEST z-index must come
        // FIRST in the DOM (screen-reader order) and keep z-index for stacking
        $issue = $this->makeIssue('published');
        $page = MagazineDtpPage::where('issue_id', $issue->id)->first();
        MagazineFrame::where('issue_id', $issue->id)->delete();
        MagazineFrame::create([
            'issue_id' => $issue->id, 'page_id' => $page->id, 'frame_type' => 'text',
            'x' => 36, 'y' => 400, 'width' => 300, 'height' => 100, 'z_index' => 1,
            'content' => ['html' => '<p>SECOND IN READING ORDER</p>'],
        ]);
        MagazineFrame::create([
            'issue_id' => $issue->id, 'page_id' => $page->id, 'frame_type' => 'text',
            'x' => 36, 'y' => 40, 'width' => 300, 'height' => 100, 'z_index' => 9,
            'content' => ['html' => '<p>FIRST IN READING ORDER</p>'],
        ]);
        $data = app(\App\Domain\Magazine\Services\DtpRenderService::class)->render($issue);
        $frames = $data['spreads'][0]['pages'][0]['frames'];
        $this->assertStringContainsString('FIRST IN READING ORDER', $frames[0]['html']);
        $this->assertStringContainsString('SECOND IN READING ORDER', $frames[1]['html']);
        $this->assertStringContainsString('z-index:9', $frames[0]['style']);
    }

    public function test_draft_issues_are_not_published(): void
    {
        $this->makeIssue('draft');
        $built = app(MagazineStaticPublisher::class)->publishForSite($this->site, $this->staging);
        $this->assertSame(0, $built);
    }
}
