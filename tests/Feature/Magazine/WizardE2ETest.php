<?php

namespace Tests\Feature\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Magazine\MagArticle;
use App\Models\Magazine\WizardSession;
use App\Models\Site;
use App\Services\Magazine\AnthropicClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WizardE2ETest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        // Mock the Anthropic client — not needed for this test since we manipulate state directly
        $mock = $this->createMock(AnthropicClient::class);
        $mock->method('isAvailable')->willReturn(true);
        $this->app->instance(AnthropicClient::class, $mock);
    }

    public function test_full_wizard_flow_produces_real_issue(): void
    {
        // ─── Step 1: Create session ───
        $response = $this->actingAsOwner()
            ->postJson('/api/v1/magazine/wizard/sessions', ['title' => 'E2E Test Issue']);

        $response->assertStatus(201);
        $sessionId = $response->json('data.id');
        $this->assertNotNull($sessionId);

        // ─── Step 1: Lock brief ───
        $brief = ['feeling' => 'quiet room', 'reader_state' => 'contemplative', 'anchors' => ['cover'], 'page_count' => 12];
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 1,
                'locked_artifact' => $brief,
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 2);

        // ─── Step 2: Lock structure ───
        $structure = [
            'articles' => [
                ['slug' => 'meditation', 'title' => 'Zen Meditation', 'pages' => 4, 'rhythm' => 'dense', 'role' => 'feature', 'justification' => 'Core piece'],
                ['slug' => 'koans', 'title' => 'Koans & Fragments', 'pages' => 2, 'rhythm' => 'breath', 'role' => 'interlude', 'justification' => 'Palate cleanser'],
            ],
        ];
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 2,
                'locked_artifact' => $structure,
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 3);

        // ─── Step 3: Lock selection ───
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 3,
                'locked_artifact' => ['selected_slug' => 'meditation'],
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 4);

        // ─── Step 4: Lock analysis (for meditation only) ───
        $analysis = [
            'article_slug' => 'meditation',
            'voice' => ['tone' => 'intimate instruction', 'register' => 'warm', 'posture' => 'contemplative'],
            'beats' => [
                ['name' => 'The Gate', 'description' => 'Reader enters'],
                ['name' => 'The Practice', 'description' => 'How to sit'],
                ['name' => 'The Return', 'description' => 'Coming back'],
            ],
            'spread_assignments' => [
                ['spread' => 1, 'beat' => 'The Gate', 'role' => 'entry', 'density' => 'breath', 'tension' => 'figure-vs-ground'],
                ['spread' => 2, 'beat' => 'The Practice', 'role' => 'argument', 'density' => 'dense', 'tension' => 'ordered-vs-disrupted'],
            ],
        ];
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 4,
                'locked_artifact' => $analysis,
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 5);

        // ─── Step 5: Lock direction ───
        $direction = [
            'article_slug' => 'meditation',
            'proposed' => [],
            'chosen' => [
                'name' => 'Stone Garden',
                'thesis' => 'Rigidly composed emptiness',
                'references' => ['Helmut Schmid'],
                'typography' => ['display' => 'Extended grotesque', 'text' => 'Humanist serif', 'scale_ratio' => '9:1', 'weight_palette' => 'Light+Bold', 'tracking_leading' => 'Wide+loose', 'signature_move' => 'Sinking baseline'],
                'grid' => ['columns' => '2-col asymmetric', 'baseline' => 'locked', 'breaks' => 'once per beat', 'break_meaning' => 'Voice got quieter'],
                'image_strategy' => ['treatment' => 'contained', 'ratio' => '30/70', 'cross_spread' => 'consistent framing'],
                'rules' => ['Max 3 elements', 'No touching margins', 'Display always uppercase'],
                'banned_moves' => ['No vertical centering', 'No text wrapping images', 'No serif display'],
                'spread_relationship' => 'Empties toward right',
            ],
        ];
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 5,
                'locked_artifact' => $direction,
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 6);

        // ─── Step 6: Lock thumbnails ───
        $thumbnails = [
            'article_slug' => 'meditation',
            'spreads' => [
                ['spread' => 1, 'weight_position' => 'center', 'zones' => [['kind' => 'image', 'rough' => 'full bleed']], 'entry_exit' => 'center in, right out', 'flagged_for_revision' => false],
                ['spread' => 2, 'weight_position' => 'left', 'zones' => [['kind' => 'text', 'rough' => 'two columns']], 'entry_exit' => 'left top', 'flagged_for_revision' => false],
            ],
        ];
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/lock", [
                'step' => 6,
                'locked_artifact' => $thumbnails,
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.current_step', 7);

        // ─── Step 7: Provision ───
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$sessionId}/provision");

        $response->assertStatus(200);
        $issueId = $response->json('issue_id');
        $this->assertNotNull($issueId);

        // ─── Verify final state ───

        // Issue
        $issue = MagazineIssue::find($issueId);
        $this->assertNotNull($issue);
        $this->assertEquals('E2E Test Issue', $issue->title);
        $this->assertEquals(6, $issue->target_page_count); // 4+2
        $this->assertEquals('quiet room', $issue->wizard_brief['feeling']);

        // Articles
        $articles = MagArticle::where('issue_id', $issueId)->orderBy('sort_order')->get();
        $this->assertCount(2, $articles);
        $this->assertEquals('meditation', $articles[0]->slug);
        $this->assertNotNull($articles[0]->wizard_plan);
        $this->assertEquals('Stone Garden', $articles[0]->wizard_plan['chosen_direction']['name']);
        $this->assertNull($articles[1]->wizard_plan); // koans: structure-only

        // Pages
        $page = $issue->linkedPage;
        $magPages = MagPage::where('page_id', $page->id)->orderBy('page_number')->get();
        $this->assertCount(6, $magPages);

        // Spread metadata on meditation pages
        $this->assertEquals('entry', $magPages[0]->spread_role);
        $this->assertEquals('figure-vs-ground', $magPages[0]->spread_tension);
        $this->assertEquals('argument', $magPages[1]->spread_role);

        // Koans pages: no spread metadata
        $this->assertNull($magPages[4]->spread_role);

        // Session finalized
        $session = WizardSession::find($sessionId);
        $this->assertEquals('provisioned', $session->status);
        $this->assertEquals($issueId, $session->provisioned_issue_id);
    }
}
