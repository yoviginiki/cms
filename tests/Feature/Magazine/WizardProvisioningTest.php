<?php

namespace Tests\Feature\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Magazine\MagArticle;
use App\Models\Magazine\WizardSession;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WizardProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private WizardSession $session;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->session = WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'Zen Issue One',
            'current_step' => 7,
            'status' => 'active',
            'step1_brief' => ['feeling' => 'quiet room', 'reader_state' => 'contemplative', 'anchors' => [], 'page_count' => 24],
            'step2_structure' => [
                'articles' => [
                    ['slug' => 'zen-meditation', 'title' => 'Zen Meditation', 'pages' => 4, 'rhythm' => 'dense', 'role' => 'anchor', 'justification' => 'Core piece'],
                    ['slug' => 'empty-brush', 'title' => 'The Empty Brush', 'pages' => 2, 'rhythm' => 'medium', 'role' => 'visual', 'justification' => 'Art piece'],
                    ['slug' => 'koans', 'title' => 'Koans', 'pages' => 2, 'rhythm' => 'breath', 'role' => 'interlude', 'justification' => 'Breathing room'],
                ],
            ],
            'step3_article_selection' => ['selected_slug' => 'zen-meditation'],
            'step4_analyses' => [
                [
                    'article_slug' => 'zen-meditation',
                    'voice' => ['tone' => 'intimate', 'register' => 'instructional', 'posture' => 'leaned-in'],
                    'beats' => [['name' => 'Opening', 'description' => 'Introduction'], ['name' => 'Practice', 'description' => 'Core teaching']],
                    'spread_assignments' => [
                        ['spread' => 1, 'beat' => 'Opening', 'role' => 'entry', 'density' => 'breath', 'tension' => 'figure-vs-ground'],
                        ['spread' => 2, 'beat' => 'Practice', 'role' => 'argument', 'density' => 'dense', 'tension' => 'density-vs-void'],
                    ],
                ],
            ],
            'step5_directions' => [
                [
                    'article_slug' => 'zen-meditation',
                    'proposed' => [],
                    'chosen' => ['name' => 'Stone Garden', 'thesis' => 'Rigidly composed emptiness'],
                ],
            ],
            'step6_thumbnails' => [
                [
                    'article_slug' => 'zen-meditation',
                    'spreads' => [
                        ['spread' => 1, 'weight_position' => 'center', 'zones' => [], 'entry_exit' => 'center in', 'flagged_for_revision' => false],
                    ],
                ],
            ],
        ]);
    }

    public function test_happy_path_provisions_full_session(): void
    {
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(200);
        $response->assertJsonStructure(['issue_id', 'redirect_url']);

        $issueId = $response->json('issue_id');

        // Issue created
        $issue = MagazineIssue::find($issueId);
        $this->assertNotNull($issue);
        $this->assertEquals('Zen Issue One', $issue->title);
        $this->assertEquals('quiet room', $issue->wizard_brief['feeling']);
        $this->assertEquals(8, $issue->target_page_count); // 4+2+2

        // Articles created
        $articles = MagArticle::where('issue_id', $issueId)->orderBy('sort_order')->get();
        $this->assertCount(3, $articles);
        $this->assertEquals('zen-meditation', $articles[0]->slug);
        $this->assertEquals(4, $articles[0]->page_count);
        $this->assertNotNull($articles[0]->wizard_plan); // has analysis
        $this->assertNull($articles[2]->wizard_plan); // koans: structure-only

        // Pages created
        $page = $issue->linkedPage;
        $this->assertNotNull($page);
        $magPages = MagPage::where('page_id', $page->id)->orderBy('page_number')->get();
        $this->assertCount(8, $magPages); // 4+2+2

        // First article's pages have spread metadata
        $this->assertEquals('entry', $magPages[0]->spread_role);
        $this->assertEquals('breath', $magPages[0]->spread_density);
        $this->assertEquals('argument', $magPages[1]->spread_role);

        // Third and fourth pages of first article: no assignment (only 2 assignments for 4 pages)
        $this->assertNull($magPages[2]->spread_role);

        // Second article's pages: no analysis, all null
        $this->assertNull($magPages[4]->spread_role);

        // Session updated
        $this->session->refresh();
        $this->assertEquals('provisioned', $this->session->status);
        $this->assertEquals($issueId, $this->session->provisioned_issue_id);
    }

    public function test_structure_only_articles_provision_with_null_wizard_plan(): void
    {
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(200);

        $articles = MagArticle::where('issue_id', $response->json('issue_id'))->get();
        $koans = $articles->firstWhere('slug', 'koans');
        $this->assertNotNull($koans);
        $this->assertNull($koans->wizard_plan);

        $emptyBrush = $articles->firstWhere('slug', 'empty-brush');
        $this->assertNull($emptyBrush->wizard_plan); // no step4 analysis for this slug
    }

    public function test_missing_brief_returns_422(): void
    {
        $this->session->update(['step1_brief' => null]);

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Step 1 (Brief) has not been completed.']);
    }

    public function test_empty_articles_returns_422(): void
    {
        $this->session->update(['step2_structure' => ['articles' => []]]);

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Step 2 (Structure) has no articles.']);
    }

    public function test_already_provisioned_returns_422(): void
    {
        $this->session->update(['status' => 'provisioned']);

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(422);
    }

    public function test_not_on_step_7_returns_422(): void
    {
        $this->session->update(['current_step' => 4]);

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(422);
    }

    public function test_another_users_session_returns_403(): void
    {
        $otherUser = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(403);
    }

    public function test_transaction_rollback_on_failure(): void
    {
        // Count before
        $issuesBefore = MagazineIssue::count();
        $articlesBefore = MagArticle::count();

        // Break the session's structure to cause a failure mid-transaction
        // by making articles contain null slug which violates NOT NULL
        $this->session->update([
            'step2_structure' => [
                'articles' => [
                    ['slug' => null, 'title' => null, 'pages' => 'not_a_number', 'rhythm' => 'dense'],
                ],
            ],
        ]);

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$this->session->id}/provision");

        $response->assertStatus(500);

        // Nothing should have been created
        $this->assertEquals($issuesBefore, MagazineIssue::count());
        $this->assertEquals($articlesBefore, MagArticle::count());

        // Session should still be active (not marked provisioned)
        $this->session->refresh();
        $this->assertEquals('active', $this->session->status);
    }
}
