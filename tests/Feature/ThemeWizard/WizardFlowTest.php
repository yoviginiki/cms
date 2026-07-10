<?php

namespace Tests\Feature\ThemeWizard;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\ThemeWizard\WizardSession;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WizardFlowTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        config(['cms.ai.api_key' => 'test-key']);
    }

    private function profileJson(string $name = 'Harbor', string $read = 'Calm maritime editorial.', string $layout = 'magazine'): string
    {
        return json_encode([
            'name' => $name,
            'design_read' => $read,
            'palette' => [
                'brand' => '#1B4965', 'accent' => '#BC4B51',
                'background' => '#F7F9FB', 'surface' => '#EDF1F5',
                'text' => '#2B3440', 'heading' => '#14202B', 'muted' => '#7C8996', 'border' => '#DCE3EA',
            ],
            'typography' => [
                'display_character' => 'neutral grotesque sans', 'body_character' => 'humanist reading serif',
                'scale' => 'balanced', 'heading_weight' => 600,
            ],
            'spacing' => 'airy', 'radius' => 'sharp', 'shadow' => 'none', 'layout' => $layout,
        ]);
    }

    /** Bind a fake AnthropicClient so both vision + nudge use scripted output. */
    private function fakeAi(array $texts): void
    {
        $fake = new class($texts) extends AnthropicClient {
            public function __construct(private array $texts, private int $i = 0) {}
            public function complete(string $model, array $sys, array $msgs, int $max = 4096, ?array $schema = null): array
            {
                $text = $this->texts[$this->i] ?? end($this->texts);
                $this->i++;
                return ['text' => $text, 'usage' => ['input' => 400, 'output' => 250, 'cache_write' => 0, 'cache_read' => 0, 'model' => $model]];
            }
        };
        $this->app->instance(AnthropicClient::class, $fake);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->image('ref.png', 800, 600);
    }

    public function test_start_from_upload_creates_a_drafting_session_with_candidate(): void
    {
        $this->fakeAi([$this->profileJson()]);

        $resp = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])
            ->assertStatus(201);

        $resp->assertJsonPath('data.status', 'drafting')
            ->assertJsonPath('data.title', 'Harbor')
            ->assertJsonPath('data.candidate.layout', 'magazine');
        // transcript has the opening + the design read
        $this->assertGreaterThanOrEqual(2, count($resp->json('data.transcript')));

        $sid = $resp->json('data.id');
        $this->assertDatabaseHas('theme_wizard_sessions', ['id' => $sid, 'status' => 'drafting']);
    }

    public function test_nudge_updates_the_candidate_and_appends_transcript(): void
    {
        $this->fakeAi([$this->profileJson(), $this->profileJson('Harbor Warm', 'Warmer now — ambered neutrals.', 'magazine')]);

        $start = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])
            ->json('data');

        $resp = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/{$start['id']}/nudge", ['instruction' => 'warmer'])
            ->assertOk();

        $resp->assertJsonPath('data.title', 'Harbor Warm');
        $this->assertStringContainsString('Warmer', implode(' ', array_column($resp->json('data.transcript'), 'text')));
        $this->assertGreaterThanOrEqual(4, count($resp->json('data.transcript'))); // 2 start + 2 nudge
    }

    public function test_preview_renders_the_candidate_through_the_showcase_frame(): void
    {
        $this->fakeAi([$this->profileJson()]);
        $start = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])->json('data');

        $html = $this->actingAsOwner()
            ->get("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/{$start['id']}/preview/showcase")
            ->assertOk()->getContent();

        $this->assertStringContainsString('#1B4965', $html);          // brand reaches the CSS
        $this->assertStringContainsString('--color-primary', $html);   // legacy alias for real blocks
    }

    public function test_accept_saves_a_real_site_theme(): void
    {
        $this->fakeAi([$this->profileJson()]);
        $start = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])->json('data');

        $resp = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/{$start['id']}/accept")
            ->assertOk();

        $themeId = $resp->json('data.theme_id');
        $theme = Theme::find($themeId);
        $this->assertNotNull($theme);
        $this->assertSame($this->site->id, $theme->site_id);
        $this->assertFalse((bool) $theme->is_system);            // site-owned, never system
        $this->assertSame('magazine', $theme->document['layout']['style']);
        $this->assertDatabaseHas('theme_wizard_sessions', ['id' => $start['id'], 'status' => 'accepted', 'theme_id' => $themeId]);
    }

    public function test_nudge_after_accept_is_rejected(): void
    {
        $this->fakeAi([$this->profileJson()]);
        $start = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])->json('data');
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/{$start['id']}/accept")->assertOk();

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/{$start['id']}/nudge", ['instruction' => 'warmer'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'This theme has already been accepted.');
    }

    public function test_cross_tenant_session_is_invisible(): void
    {
        $this->fakeAi([$this->profileJson()]);
        $start = $this->actingAsOwner()
            ->post("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-upload", ['image' => $this->upload()])->json('data');

        // tenant B cannot see it
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);
        $siteB = Site::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userB)
            ->getJson("/api/v1/sites/{$siteB->id}/theme-wizard/sessions/{$start['id']}")
            ->assertStatus(404);
    }
}
