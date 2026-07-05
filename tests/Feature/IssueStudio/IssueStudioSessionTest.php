<?php

namespace Tests\Feature\IssueStudio;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IssueStudioSessionTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        config(['cms.ai.api_key' => 'test-key']);
    }

    private function fakeInterviewTurn(array $overrides = []): void
    {
        $body = array_merge([
            'reply' => 'Lovely — a food magazine. Shall we call it "Salt"?',
            'brief_patch' => [
                'topic' => 'street food',
                'working_title' => 'Salt',
                'audience' => null,
                'tone' => null,
                'genre' => 'lifestyle',
                'page_ambition' => null,
                'note' => 'Type-led if images stay sparse.',
            ],
            'interview_complete' => false,
        ], $overrides);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($body)]],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 900,
                    'output_tokens' => 120,
                    'cache_creation_input_tokens' => 800,
                    'cache_read_input_tokens' => 0,
                ],
            ]),
        ]);
    }

    public function test_owner_can_create_and_fetch_a_session(): void
    {
        $create = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'interviewing')
            ->assertJsonPath('data.brief.topic', null)
            ->assertJsonPath('data.brief.materials', []);

        $id = $create->json('data.id');

        $this->actingAsOwner()->getJson("/api/v1/issue-studio/sessions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.transcript', []);
    }

    public function test_editor_role_is_rejected(): void
    {
        $this->actingAsEditor()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->assertForbidden();
    }

    public function test_chat_turn_updates_brief_and_transcript_and_logs_tokens(): void
    {
        $this->fakeInterviewTurn();

        $id = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->json('data.id');

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/messages", [
            'content' => 'I want a magazine about street food',
        ]);

        $resp->assertOk()
            ->assertJsonPath('data.brief.topic', 'street food')
            ->assertJsonPath('data.brief.genre', 'lifestyle')
            ->assertJsonPath('data.title', 'Salt')
            ->assertJsonPath('data.status', 'interviewing')
            ->assertJsonPath('data.transcript.0.role', 'user')
            ->assertJsonPath('data.transcript.1.role', 'assistant')
            ->assertJsonPath('data.token_usage.0.phase', 'interview')
            ->assertJsonPath('data.token_usage.0.input', 900)
            ->assertJsonPath('data.token_usage.0.cache_write', 800);

        // budget tracking hit the tenant counter
        $this->assertSame(1020, (int) $this->tenant->fresh()->monthly_tokens_used);
    }

    public function test_interview_complete_moves_session_to_flatplanning(): void
    {
        $this->fakeInterviewTurn(['interview_complete' => true, 'reply' => 'Planning now.']);

        $id = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->json('data.id');

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/messages", [
            'content' => 'just do it',
        ])->assertOk()->assertJsonPath('data.status', 'flatplanning');

        // Past the interview, further messages are rejected
        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/messages", [
            'content' => 'one more thing',
        ])->assertStatus(422);
    }

    public function test_materials_can_be_added_and_removed(): void
    {
        $id = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->json('data.id');

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/materials", [
            'kind' => 'text',
            'title' => 'Night markets essay',
            'content' => str_repeat('word ', 500),
        ]);

        $resp->assertOk()
            ->assertJsonPath('data.brief.materials.0.title', 'Night markets essay')
            ->assertJsonPath('data.brief.materials.0.word_count', 500);

        $materialId = $resp->json('data.brief.materials.0.id');

        $this->actingAsOwner()
            ->deleteJson("/api/v1/issue-studio/sessions/{$id}/materials/{$materialId}")
            ->assertOk()
            ->assertJsonPath('data.brief.materials', []);

        // image material requires an asset id
        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/materials", [
            'kind' => 'image',
            'title' => 'Hero shot',
        ])->assertStatus(422);
    }

    public function test_budget_exhaustion_blocks_the_turn(): void
    {
        $this->fakeInterviewTurn();
        \Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenant->id)->update([
            'monthly_token_budget' => 100,
            'monthly_tokens_used' => 150,
            'token_usage_reset_at' => now()->addDays(10),
        ]);

        $id = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->json('data.id');

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$id}/messages", [
            'content' => 'hello',
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_cross_tenant_session_is_invisible(): void
    {
        $id = $this->actingAsOwner()->postJson('/api/v1/issue-studio/sessions', [
            'site_id' => $this->site->id,
        ])->json('data.id');

        $otherTenant = \App\Models\Tenant::factory()->create();
        $outsider = \App\Models\User::factory()->owner()->create(['tenant_id' => $otherTenant->id]);
        $this->setTenantScope($outsider);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/issue-studio/sessions/{$id}")
            ->assertNotFound();
    }
}
