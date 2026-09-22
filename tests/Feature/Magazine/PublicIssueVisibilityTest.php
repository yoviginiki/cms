<?php

namespace Tests\Feature\Magazine;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * F23 (audit 2026-09-22) — the public /issue/{slug} route rendered any
 * magazine-mode page regardless of its status; drafts and archived issues
 * must be 404 for anonymous visitors.
 */
class PublicIssueVisibilityTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'mag.test']);
    }

    private function issue(string $status): Page
    {
        return Page::factory()->create([
            'site_id' => $this->site->id,
            'slug' => 'issue-' . $status,
            'status' => $status,
            'editor_mode' => 'magazine',
        ]);
    }

    public function test_only_published_issues_are_public(): void
    {
        $this->issue('draft');
        $this->issue('archived');
        $this->issue('published');

        $this->get('http://mag.test/issue/issue-draft')->assertNotFound();
        $this->get('http://mag.test/issue/issue-archived')->assertNotFound();
        $this->get('http://mag.test/issue/issue-published')->assertOk();
    }
}
