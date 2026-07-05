<?php

namespace Tests\Feature\Api;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDocVersion;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;
use App\Domain\Magazine\Services\DtpDocumentService;
use App\Models\Site;
use Tests\TestCase;

class DtpVersionsTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeIssueWithDoc(string $text): MagazineIssue
    {
        $issue = MagazineIssue::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'title' => 'Versioned Issue',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);
        $spread = MagazineSpread::create(['issue_id' => $issue->id, 'spread_index' => 0]);
        $page = MagazineDtpPage::create([
            'issue_id' => $issue->id, 'spread_id' => $spread->id,
            'page_index' => 0, 'side' => 'single', 'width' => 595, 'height' => 842,
        ]);
        MagazineFrame::create([
            'issue_id' => $issue->id, 'page_id' => $page->id,
            'frame_type' => 'text', 'x' => 36, 'y' => 36, 'width' => 200, 'height' => 100,
            'z_index' => 1, 'content' => ['html' => $text],
        ]);

        return $issue;
    }

    public function test_save_snapshots_previous_state_and_restore_brings_it_back(): void
    {
        $svc = app(DtpDocumentService::class);
        $issue = $this->makeIssueWithDoc('ORIGINAL WORDS');
        $docV1 = $svc->loadDocument($issue);

        // save a CHANGED doc — v1 must be snapshotted first
        $changed = $docV1;
        $changed['frames'] = collect($docV1['frames'])->map(function ($f) {
            $f['content'] = ['html' => 'CHANGED WORDS'];

            return $f;
        })->all();
        $svc->saveDocument($issue, $changed);

        $versions = MagazineDocVersion::where('issue_id', $issue->id)->get();
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->frame_count);
        $this->assertStringContainsString('ORIGINAL WORDS', json_encode($versions[0]->document));

        // restore = feed the snapshot back through saveDocument
        $svc->saveDocument($issue, $versions[0]->document);
        $now = $svc->loadDocument($issue);
        $this->assertStringContainsString('ORIGINAL WORDS', json_encode($now['frames']));
        // the restore snapshotted the CHANGED state too
        $this->assertCount(2, MagazineDocVersion::where('issue_id', $issue->id)->get());
    }

    public function test_version_trail_is_capped(): void
    {
        $svc = app(DtpDocumentService::class);
        $issue = $this->makeIssueWithDoc('CAP TEST');
        for ($i = 0; $i < 25; $i++) {
            $svc->snapshotVersion($issue);
        }
        $this->assertLessThanOrEqual(21, MagazineDocVersion::where('issue_id', $issue->id)->count());
    }

    public function test_empty_document_is_not_snapshotted(): void
    {
        $issue = MagazineIssue::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'title' => 'Empty',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);
        $this->assertNull(app(DtpDocumentService::class)->snapshotVersion($issue));
    }
}
