<?php

namespace Tests\Feature\IssueStudio;

use App\Models\IssueStudio\StudioSession;
use App\Models\Site;
use App\Services\IssueStudio\Playbook;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlatplanTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        config(['cms.ai.api_key' => 'test-key']);
    }

    private function makeSession(string $status = 'flatplanning', ?array $flatplan = null): StudioSession
    {
        return StudioSession::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'user_id' => $this->owner->id,
            'status' => $status,
            'brief' => [
                'topic' => 'street food',
                'working_title' => 'Salt',
                'audience' => 'curious eaters',
                'tone' => 'warm',
                'genre' => 'lifestyle',
                'page_ambition' => null,
                'notes' => [],
                'materials' => [
                    ['id' => 'm-text1', 'kind' => 'text', 'title' => 'Night markets', 'content' => str_repeat('word ', 900), 'word_count' => 900],
                    ['id' => 'm-img1', 'kind' => 'image', 'title' => 'Grill hero', 'asset_id' => 'a1'],
                ],
            ],
            'transcript' => [],
            'flatplan' => $flatplan,
            'token_usage' => [],
        ]);
    }

    private function validSpreads(): array
    {
        return [
            ['position' => 0, 'working_title' => 'Salt', 'section' => 'cover', 'pattern' => 'cover-image', 'materials' => ['m-img1'], 'intent' => 'One strong image sells the issue.'],
            ['position' => 1, 'working_title' => 'Night markets', 'section' => 'feature', 'pattern' => 'full-bleed-opener', 'materials' => ['m-img1', 'm-text1'], 'intent' => 'Open loud with the grill image.'],
            ['position' => 2, 'working_title' => 'Night markets II', 'section' => 'feature', 'pattern' => 'text-well-two-column', 'materials' => ['m-text1'], 'intent' => 'The essay body, quiet after the loud opener.'],
            ['position' => 3, 'working_title' => 'Colophon', 'section' => 'bob', 'pattern' => 'closer-colophon', 'materials' => [], 'intent' => 'End deliberately.'],
        ];
    }

    private function opusResponse(array $payload): array
    {
        return [
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 5000, 'output_tokens' => 700, 'cache_creation_input_tokens' => 4500, 'cache_read_input_tokens' => 0],
        ];
    }

    public function test_pattern_vocabulary_is_parsed_from_the_playbook(): void
    {
        $names = app(Playbook::class)->patternNames();

        $this->assertContains('cover-image', $names['covers']);
        $this->assertContains('cover-type', $names['covers']);
        $this->assertContains('closer-colophon', $names['spreads']);
        $this->assertContains('full-bleed-opener', $names['spreads']);
        $this->assertGreaterThanOrEqual(20, count($names['spreads']));
    }

    public function test_generate_stores_a_valid_flatplan(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->opusResponse(['spreads' => $this->validSpreads()]))]);
        $session = $this->makeSession();

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/generate");

        $resp->assertOk()
            ->assertJsonPath('data.flatplan.approved', false)
            ->assertJsonPath('data.flatplan.spreads.0.pattern', 'cover-image')
            ->assertJsonPath('data.flatplan.spreads.3.pattern', 'closer-colophon')
            ->assertJsonPath('data.token_usage.0.phase', 'flatplan');
    }

    public function test_invalid_flatplan_gets_one_repair_round_trip(): void
    {
        $bad = $this->validSpreads();
        $bad[1]['pattern'] = 'made-up-pattern';

        Http::fakeSequence()
            ->push($this->opusResponse(['spreads' => $bad]))
            ->push($this->opusResponse(['spreads' => $this->validSpreads()]));

        $session = $this->makeSession();

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/generate")
            ->assertOk()
            ->assertJsonPath('data.flatplan.spreads.1.pattern', 'full-bleed-opener');

        Http::assertSentCount(2);
        // both calls were logged against the budget
        $this->assertCount(2, $session->fresh()->token_usage);
    }

    public function test_twice_invalid_flatplan_fails_gracefully(): void
    {
        $bad = $this->validSpreads();
        $bad[0]['pattern'] = 'full-bleed-opener'; // no cover treatment at 0

        Http::fakeSequence()
            ->push($this->opusResponse(['spreads' => $bad]))
            ->push($this->opusResponse(['spreads' => $bad]));

        $session = $this->makeSession();

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/generate");

        $resp->assertStatus(422);
        $this->assertStringContainsString('cover', strtolower($resp->json('error')));
        $this->assertNull($session->fresh()->flatplan);
    }

    public function test_generate_is_blocked_during_interview(): void
    {
        Http::fake();
        $session = $this->makeSession('interviewing');

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/generate")
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_reorder_swaps_middle_spreads_and_rewrites_positions(): void
    {
        $session = $this->makeSession('flatplanning', ['spreads' => $this->validSpreads(), 'approved' => false, 'generated_at' => now()->toIso8601String()]);

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/reorder", [
            'order' => [0, 2, 1, 3],
        ])->assertOk()
            ->assertJsonPath('data.flatplan.spreads.1.working_title', 'Night markets II')
            ->assertJsonPath('data.flatplan.spreads.1.position', 1)
            ->assertJsonPath('data.flatplan.spreads.2.working_title', 'Night markets');
    }

    public function test_reorder_cannot_displace_the_cover(): void
    {
        $session = $this->makeSession('flatplanning', ['spreads' => $this->validSpreads(), 'approved' => false, 'generated_at' => now()->toIso8601String()]);

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/reorder", [
            'order' => [1, 0, 2, 3],
        ])->assertStatus(422);
    }

    public function test_revise_replaces_a_single_spread(): void
    {
        $revised = [
            'position' => 2,
            'working_title' => 'Night markets II',
            'section' => 'feature',
            'pattern' => 'image-interruption',
            'materials' => ['m-img1'],
            'intent' => 'A breath: the image alone.',
        ];
        Http::fake(['api.anthropic.com/*' => Http::response($this->opusResponse($revised))]);

        $session = $this->makeSession('flatplanning', ['spreads' => $this->validSpreads(), 'approved' => false, 'generated_at' => now()->toIso8601String()]);

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/revise", [
            'position' => 2,
            'instruction' => 'make this image-led',
        ])->assertOk()
            ->assertJsonPath('data.flatplan.spreads.2.pattern', 'image-interruption')
            ->assertJsonPath('data.flatplan.spreads.1.pattern', 'full-bleed-opener');
    }

    public function test_approve_locks_flatplan_and_creates_spread_rows(): void
    {
        $session = $this->makeSession('flatplanning', ['spreads' => $this->validSpreads(), 'approved' => false, 'generated_at' => now()->toIso8601String()]);

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/approve");

        $resp->assertOk()
            ->assertJsonPath('data.status', 'generating')
            ->assertJsonPath('data.flatplan.approved', true);

        $rows = $session->fresh()->spreads;
        $this->assertCount(4, $rows);
        $this->assertSame('pending', $rows[0]->status);
        $this->assertSame('cover-image', $rows[0]->pattern);
        $this->assertSame(3, $rows[3]->position);

        // locked: no more edits
        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/reorder", [
            'order' => [0, 2, 1, 3],
        ])->assertStatus(422);
        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/generate")
            ->assertStatus(422);
    }

    public function test_approve_without_flatplan_is_rejected(): void
    {
        $session = $this->makeSession();

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/flatplan/approve")
            ->assertStatus(422);
    }
}
