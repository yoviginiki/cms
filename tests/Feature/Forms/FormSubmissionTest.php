<?php

namespace Tests\Feature\Forms;

use App\Domain\Blocks\Services\BlockService;
use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * S5 Forms v2 — the previously-untested submit path: block-schema
 * validation, honeypot + time trap, DB storage under RLS, response
 * negotiation (JSON vs 303-redirect), notification email, admin endpoints.
 */
class FormSubmissionTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);

        app(BlockService::class)->syncBlocks($this->page, [[
            'type' => 'customform',
            'order' => 0,
            'data' => [
                'formKey' => 'inquiry',
                'notifyEmail' => 'gallery@example.com',
                'fields' => [
                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                    ['label' => 'Email', 'type' => 'email', 'required' => true],
                    ['label' => 'Message', 'type' => 'textarea', 'required' => false],
                    ['label' => 'Topic', 'type' => 'select', 'options' => ['Price', 'Shipping']],
                ],
            ],
        ]]);
    }

    private function submit(array $payload, array $headers = [])
    {
        return $this->postJson("/api/v1/sites/{$this->site->id}/forms/inquiry/submit", $payload, $headers);
    }

    public function test_valid_submission_stores_labeled_data_and_drops_unknown_fields(): void
    {
        Mail::shouldReceive('raw')->once();

        $this->submit([
            'name' => 'Rilke',
            'email' => 'rilke@example.com',
            'message' => 'Is the harbor etching available?',
            'topic' => 'Price',
            'evil_extra' => 'dropped',
        ])->assertOk()->assertJson(['success' => true]);

        $row = FormSubmission::firstOrFail();
        $this->assertSame('inquiry', $row->form_key);
        $this->assertSame($this->site->id, $row->site_id);
        $this->assertSame('Rilke', $row->data['Name']);
        $this->assertSame('Price', $row->data['Topic']);
        $this->assertArrayNotHasKey('evil_extra', $row->data);
        $this->assertArrayNotHasKey('Evil extra', $row->data);
    }

    public function test_honeypot_pretends_success_and_stores_nothing(): void
    {
        Mail::shouldReceive('raw')->never();

        $this->submit(['name' => 'Bot', 'email' => 'b@example.com', '_honeypot' => 'gotcha'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_time_trap_blocks_instant_submits_but_allows_no_js(): void
    {
        // _t set to "now" = filled in under 3s → silently dropped
        $this->submit(['name' => 'Fast', 'email' => 'f@example.com', '_t' => now()->getTimestampMs()])
            ->assertOk();
        $this->assertSame(0, FormSubmission::count());

        // _t absent (no-JS visitor) → accepted
        $this->submit(['name' => 'Slow', 'email' => 's@example.com'])->assertOk();
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_block_schema_is_enforced_server_side(): void
    {
        // required missing
        $this->submit(['email' => 'x@example.com'])->assertStatus(422);
        // bad email
        $this->submit(['name' => 'A', 'email' => 'not-an-email'])->assertStatus(422);
        // select outside options
        $this->submit(['name' => 'A', 'email' => 'a@example.com', 'topic' => 'Hacking'])->assertStatus(422);
        // unknown form key
        $this->postJson("/api/v1/sites/{$this->site->id}/forms/nope/submit", ['name' => 'A'])->assertStatus(404);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_plain_post_redirects_back_with_success_anchor(): void
    {
        $response = $this->post("/api/v1/sites/{$this->site->id}/forms/inquiry/submit", [
            'name' => 'NoJs',
            'email' => 'nojs@example.com',
        ], ['Referer' => 'https://tenant.example/products/harbor-etching/', 'Accept' => 'text/html']);

        $response->assertStatus(303);
        $response->assertRedirect('https://tenant.example/products/harbor-etching/#form-inquiry-success');
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_legacy_contact_route_stores_under_contact_key(): void
    {
        app(BlockService::class)->syncBlocks($this->page, [[
            'type' => 'contact-form',
            'order' => 0,
            'data' => ['recipient_email' => 'owner@example.com'],
        ]]);

        Mail::shouldReceive('raw')->once();
        $this->postJson("/api/v1/sites/{$this->site->id}/forms/submit", [
            'name' => 'Legacy', 'email' => 'l@example.com', 'message' => 'Hi',
        ])->assertOk();

        $row = FormSubmission::firstOrFail();
        $this->assertSame('contact', $row->form_key);
        $this->assertSame('Legacy', $row->data['Name']);
    }

    public function test_admin_list_filter_delete_and_export(): void
    {
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'inquiry', 'data' => ['Name' => 'A', 'Email' => 'a@x.com']]);
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'contact', 'data' => ['Name' => 'B', 'Email' => 'b@x.com']]);

        $list = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/form-submissions")
            ->assertOk()->json();
        $this->assertSame(2, $list['meta']['total']);
        $this->assertEqualsCanonicalizing(['inquiry', 'contact'], $list['meta']['form_keys']);

        $filtered = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/form-submissions?form_key=inquiry")
            ->assertOk()->json('data');
        $this->assertCount(1, $filtered);
        $this->assertSame('A', $filtered[0]['data']['Name']);

        $csv = $this->actingAsOwner()->get("/api/v1/sites/{$this->site->id}/form-submissions/export?form_key=inquiry")
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Name', $csv);
        $this->assertStringContainsString('a@x.com', $csv);
        $this->assertStringNotContainsString('b@x.com', $csv);

        $id = $filtered[0]['id'];
        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$this->site->id}/form-submissions/{$id}")->assertOk();
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_notification_failure_still_stores_the_submission(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp down'));

        $this->submit(['name' => 'Resilient', 'email' => 'r@example.com'])->assertOk();
        $this->assertSame(1, FormSubmission::count());
    }
}
