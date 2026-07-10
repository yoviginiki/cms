<?php

namespace Tests\Feature\IssueStudio;

use App\Models\IssueStudio\StudioSession;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpreadGenerationTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        config(['cms.ai.api_key' => 'test-key']);
    }

    /** Session in generating state with an approved 3-slot flatplan + spread rows. */
    private function makeGeneratingSession(): StudioSession
    {
        $flatplanSpreads = [
            ['position' => 0, 'working_title' => 'Salt', 'section' => 'cover', 'pattern' => 'cover-image', 'materials' => ['m-img1'], 'intent' => 'One image sells it.'],
            ['position' => 1, 'working_title' => 'Night markets', 'section' => 'feature', 'pattern' => 'full-bleed-opener', 'materials' => ['m-img1', 'm-text1'], 'intent' => 'Open loud.'],
            ['position' => 2, 'working_title' => 'Colophon', 'section' => 'bob', 'pattern' => 'closer-colophon', 'materials' => [], 'intent' => 'End quiet.'],
        ];

        $session = StudioSession::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $this->site->id,
            'user_id' => $this->owner->id,
            'status' => 'generating',
            'title' => 'Salt',
            'brief' => [
                'topic' => 'street food',
                'working_title' => 'Salt',
                'audience' => 'eaters',
                'tone' => 'warm',
                'genre' => 'lifestyle',
                'page_ambition' => null,
                'notes' => [],
                'materials' => [
                    ['id' => 'm-text1', 'kind' => 'text', 'title' => 'Night markets', 'content' => '<p>' . str_repeat('word ', 300) . '</p>', 'word_count' => 300],
                    ['id' => 'm-img1', 'kind' => 'image', 'title' => 'Grill hero', 'asset_id' => '019f0000-0000-7000-8000-000000000001'],
                ],
            ],
            'transcript' => [],
            'flatplan' => ['spreads' => $flatplanSpreads, 'approved' => true, 'generated_at' => now()->toIso8601String()],
            'token_usage' => [],
        ]);

        foreach ($flatplanSpreads as $fp) {
            $session->spreads()->create([
                'tenant_id' => $session->tenant_id,
                'position' => $fp['position'],
                'status' => 'pending',
                'working_title' => $fp['working_title'],
                'section' => $fp['section'],
                'pattern' => $fp['pattern'],
                'materials' => $fp['materials'],
                'intent' => $fp['intent'],
            ]);
        }

        return $session;
    }

    private function coverDoc(string $note = 'Full-bleed cover because the grill image is the strongest material.'): array
    {
        return [
            'editorial_note' => $note,
            'pages' => [[
                'side' => 'single',
                'elements' => [
                    ['type' => 'background_image', 'x' => 0, 'y' => 0, 'w' => 595, 'h' => 842, 'material_id' => 'm-img1', 'alt' => 'Grill at night', 'fit_mode' => 'fill', 'focal_x' => 0.5, 'focal_y' => 0.4, 'z' => 0],
                    ['type' => 'gradient_overlay', 'x' => 0, 'y' => 560, 'w' => 595, 'h' => 282, 'fill_color' => '#000000', 'opacity' => 40, 'z' => 5],
                    ['type' => 'headline_frame', 'x' => 50, 'y' => 590, 'w' => 420, 'h' => 90, 'html' => '<p>Salt</p>', 'font_size' => 64, 'font_family' => 'Barlow Condensed', 'text_color' => '#ffffff', 'z' => 10],
                    ['type' => 'text_frame', 'x' => 50, 'y' => 700, 'w' => 300, 'h' => 50, 'html' => '<p>Street food, honestly.</p>', 'font_size' => 12, 'text_color' => '#ffffff', 'z' => 10],
                ],
            ]],
        ];
    }

    private function spreadDoc(string $bodyText = 'The market opens at dusk.'): array
    {
        return [
            'editorial_note' => 'Image left, one quiet column right.',
            'pages' => [
                [
                    'side' => 'left',
                    'elements' => [
                        ['type' => 'fullbleed_image', 'x' => 0, 'y' => 0, 'w' => 595, 'h' => 842, 'material_id' => 'm-img1', 'alt' => 'Night market', 'fit_mode' => 'fill', 'z' => 0],
                        ['type' => 'caption_frame', 'x' => 36, 'y' => 780, 'w' => 220, 'h' => 22, 'html' => '<p>The night market, 9pm.</p>', 'font_size' => 8, 'z' => 10],
                    ],
                ],
                [
                    'side' => 'right',
                    'elements' => [
                        ['type' => 'headline_frame', 'x' => 60, 'y' => 80, 'w' => 440, 'h' => 80, 'html' => '<p>Night markets</p>', 'font_size' => 48, 'z' => 10],
                        ['type' => 'text_frame', 'x' => 60, 'y' => 200, 'w' => 220, 'h' => 560, 'html' => '<p>' . $bodyText . '</p>', 'font_size' => 10, 'columns' => 1, 'z' => 10],
                    ],
                ],
            ],
        ];
    }

    private function closerDoc(): array
    {
        return [
            'editorial_note' => 'A quiet ending.',
            'pages' => [
                ['side' => 'left', 'elements' => [
                    ['type' => 'rectangle', 'x' => 0, 'y' => 0, 'w' => 595, 'h' => 842, 'fill_color' => '#f6f6f4', 'z' => 0],
                ]],
                ['side' => 'right', 'elements' => [
                    ['type' => 'text_frame', 'x' => 200, 'y' => 400, 'w' => 195, 'h' => 120, 'html' => '<p>Made with Issue Studio.</p>', 'font_size' => 9, 'z' => 5],
                    ['type' => 'decorative_rule', 'x' => 200, 'y' => 560, 'w' => 130, 'h' => 3, 'z' => 5],
                ]],
            ],
        ];
    }

    private function opusResponse(array $payload): array
    {
        return [
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 9000, 'output_tokens' => 1500, 'cache_creation_input_tokens' => 8000, 'cache_read_input_tokens' => 0],
        ];
    }

    public function test_generate_next_creates_a_real_magazine_document(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->opusResponse($this->coverDoc()))]);
        $session = $this->makeGeneratingSession();

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next");

        $resp->assertOk()
            ->assertJsonPath('data.spreads.0.status', 'generated')
            ->assertJsonPath('data.spreads.1.status', 'pending');

        $session->refresh();
        $this->assertNotNull($session->magazine_issue_id);

        // real DTP rows exist
        $this->assertSame(1, DB::table('magazine_dtp_pages')->where('issue_id', $session->magazine_issue_id)->count());
        $this->assertSame(4, DB::table('magazine_frames')->where('issue_id', $session->magazine_issue_id)->count());

        $cover = $session->spreads()->where('position', 0)->first();
        $this->assertCount(1, $cover->page_ids);
        $this->assertStringContainsString('strongest material', $cover->generation_notes[0]['note']);

        // image frame carries the asset-backed src and sanitized content
        $img = DB::table('magazine_frames')->where('issue_id', $session->magazine_issue_id)->where('frame_type', 'image')->first();
        $content = json_decode($img->content, true);
        $this->assertSame("/api/v1/sites/{$session->site_id}/assets/019f0000-0000-7000-8000-000000000001/serve", $content['src']);

        // entity references: doc->asset + spread->issue
        $this->assertDatabaseHas('entity_references', [
            'source_type' => 'issue_studio_spread',
            'source_id' => $cover->id,
            'target_type' => 'magazine_issue',
            'target_id' => $session->magazine_issue_id,
        ]);
        $this->assertDatabaseHas('entity_references', [
            'source_type' => 'magazine_doc',
            'source_id' => $session->magazine_issue_id,
            'target_type' => 'asset',
        ]);
    }

    public function test_image_placeholder_without_material_composes_and_renders_a_slot(): void
    {
        // a cover that reserves a picture slot (empty material_id) the user fills later
        $doc = [
            'editorial_note' => 'A reserved hero picture the user drops their own photo into.',
            'pages' => [[
                'side' => 'single',
                'elements' => [
                    ['type' => 'fullbleed_image', 'x' => 0, 'y' => 0, 'w' => 595, 'h' => 500, 'material_id' => '', 'alt' => 'a calm close-up of hands in zazen', 'fit_mode' => 'fill', 'z' => 0],
                    ['type' => 'headline_frame', 'x' => 50, 'y' => 540, 'w' => 420, 'h' => 90, 'html' => '<p>Salt</p>', 'font_size' => 64, 'z' => 10],
                ],
            ]],
        ];
        Http::fake(['api.anthropic.com/*' => Http::response($this->opusResponse($doc))]);
        $session = $this->makeGeneratingSession();

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next")
            ->assertOk()->assertJsonPath('data.spreads.0.status', 'generated');
        $session->refresh();

        // the image frame exists as a placeholder — no src, flagged, alt kept
        $img = DB::table('magazine_frames')->where('issue_id', $session->magazine_issue_id)->where('frame_type', 'image')->first();
        $this->assertNotNull($img, 'placeholder image frame should be composed, not dropped');
        $content = json_decode($img->content, true);
        $this->assertArrayNotHasKey('src', $content);
        $this->assertTrue($content['placeholder'] ?? false);
        $this->assertSame('a calm close-up of hands in zazen', $content['alt']);

        // it renders as a fillable slot (dashed box + the art-direction note)
        $data = app(\App\Domain\Magazine\Services\DtpRenderService::class)
            ->render(\App\Domain\IssueComposer\Models\MagazineIssue::find($session->magazine_issue_id));
        $html = json_encode($data['spreads']);
        $this->assertStringContainsString('a calm close-up of hands in zazen', $html);
        $this->assertStringContainsString('dashed', $html);
    }

    public function test_generate_next_blocked_until_current_spread_is_decided(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->opusResponse($this->coverDoc()))]);
        $session = $this->makeGeneratingSession();

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next")->assertOk();

        $resp = $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next");
        $resp->assertStatus(422);
        $this->assertStringContainsString('keep, revise or rethink', $resp->json('error'));
    }

    public function test_full_loop_keep_all_completes_the_session(): void
    {
        Http::fakeSequence()
            ->push($this->opusResponse($this->coverDoc()))
            ->push($this->opusResponse($this->spreadDoc()))
            ->push($this->opusResponse($this->closerDoc()));

        $session = $this->makeGeneratingSession();
        $base = "/api/v1/issue-studio/sessions/{$session->id}";

        foreach ([0, 1, 2] as $position) {
            $this->actingAsOwner()->postJson("{$base}/spreads/generate-next")->assertOk();
            $this->actingAsOwner()->postJson("{$base}/spreads/{$position}/keep")->assertOk();
        }

        $session->refresh();
        $this->assertSame('complete', $session->status);
        // cover page 0 + spreads at 1,2 and 3,4 = 5 pages
        $this->assertSame(5, DB::table('magazine_dtp_pages')->where('issue_id', $session->magazine_issue_id)->count());

        // spread 2 owns page_index 3+4
        $closer = $session->spreads()->where('position', 2)->first();
        $this->assertCount(2, $closer->page_ids);
        $indexes = DB::table('magazine_dtp_pages')->whereIn('id', $closer->page_ids)->pluck('page_index')->sort()->values()->all();
        $this->assertSame([3, 4], $indexes);
    }

    public function test_revise_replaces_the_spread_document(): void
    {
        Http::fakeSequence()
            ->push($this->opusResponse($this->coverDoc()))
            ->push($this->opusResponse($this->coverDoc('Quieter now: smaller type, more air.')));

        $session = $this->makeGeneratingSession();
        $base = "/api/v1/issue-studio/sessions/{$session->id}";

        $this->actingAsOwner()->postJson("{$base}/spreads/generate-next")->assertOk();
        $before = DB::table('magazine_frames')->where('issue_id', $session->fresh()->magazine_issue_id)->pluck('id')->all();

        $this->actingAsOwner()->postJson("{$base}/spreads/0/revise", ['instruction' => 'make it quieter'])
            ->assertOk()
            ->assertJsonPath('data.spreads.0.status', 'generated');

        $after = DB::table('magazine_frames')->where('issue_id', $session->fresh()->magazine_issue_id)->pluck('id')->all();
        $this->assertNotEquals($before, $after); // full replace happened
        $this->assertSame(count($before), count($after));

        $notes = $session->spreads()->where('position', 0)->first()->generation_notes;
        $this->assertCount(2, $notes);
        $this->assertSame('spread-revise', $notes[1]['phase']);
    }

    public function test_rethink_with_alternative_pattern_updates_slot_and_flatplan(): void
    {
        Http::fakeSequence()
            ->push($this->opusResponse($this->coverDoc()))
            ->push($this->opusResponse($this->coverDoc('Type-led now.')));

        $session = $this->makeGeneratingSession();
        $base = "/api/v1/issue-studio/sessions/{$session->id}";

        $this->actingAsOwner()->postJson("{$base}/spreads/generate-next")->assertOk();

        $this->actingAsOwner()->postJson("{$base}/spreads/0/rethink", ['pattern' => 'cover-type'])
            ->assertOk()
            ->assertJsonPath('data.spreads.0.pattern', 'cover-type')
            ->assertJsonPath('data.flatplan.spreads.0.pattern', 'cover-type');

        // spread patterns are rejected for the cover slot
        $this->actingAsOwner()->postJson("{$base}/spreads/0/rethink", ['pattern' => 'stat-punch'])
            ->assertStatus(422);
    }

    public function test_invalid_document_gets_one_repair_round_trip(): void
    {
        $bad = $this->coverDoc();
        $bad['pages'][0]['elements'][0]['material_id'] = 'm-missing';

        Http::fakeSequence()
            ->push($this->opusResponse($bad))
            ->push($this->opusResponse($this->coverDoc()));

        $session = $this->makeGeneratingSession();

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next")
            ->assertOk()
            ->assertJsonPath('data.spreads.0.status', 'generated');

        Http::assertSentCount(2);
    }

    public function test_generation_requires_generating_status(): void
    {
        Http::fake();
        $session = $this->makeGeneratingSession();
        $session->update(['status' => 'flatplanning']);

        $this->actingAsOwner()->postJson("/api/v1/issue-studio/sessions/{$session->id}/spreads/generate-next")
            ->assertStatus(422);
        Http::assertNothingSent();
    }
}
