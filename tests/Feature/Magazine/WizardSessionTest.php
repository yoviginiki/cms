<?php

namespace Tests\Feature\Magazine;

use App\Models\Magazine\WizardSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WizardSessionTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(User $user): void
    {
        $tid = preg_replace('/[^a-f0-9\-]/', '', $user->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$tid}'");
    }

    public function test_user_can_create_a_session(): void
    {
        $this->setTenant($this->owner);

        $response = $this->actingAsOwner()
            ->postJson('/api/v1/magazine/wizard/sessions', [
                'title' => 'Zen Issue 1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Zen Issue 1')
            ->assertJsonPath('data.current_step', 1)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('mag_wizard_sessions', [
            'title' => 'Zen Issue 1',
            'user_id' => $this->owner->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_create_session_without_title(): void
    {
        $this->setTenant($this->owner);

        $response = $this->actingAsOwner()
            ->postJson('/api/v1/magazine/wizard/sessions');

        $response->assertStatus(201)
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.current_step', 1);
    }

    public function test_user_sees_only_their_own_sessions(): void
    {
        $this->setTenant($this->owner);

        // Create sessions for the owner
        WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'Owner Session',
            'current_step' => 1,
            'status' => 'active',
        ]);

        // Create another user in the same tenant
        $otherUser = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'title' => 'Other User Session',
            'current_step' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()
            ->getJson('/api/v1/magazine/wizard/sessions');

        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Owner Session', $titles);
        $this->assertNotContains('Other User Session', $titles);
    }

    public function test_another_tenants_sessions_are_never_visible(): void
    {
        // Create a second tenant + user
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->owner()->create(['tenant_id' => $tenant2->id]);

        // Create session in tenant 2
        $tid2 = preg_replace('/[^a-f0-9\-]/', '', $tenant2->id);
        DB::unprepared("SET app.current_tenant_id = '{$tid2}'");
        WizardSession::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'title' => 'Secret Session',
            'current_step' => 1,
            'status' => 'active',
        ]);

        // Now query as tenant 1
        $this->setTenant($this->owner);

        $response = $this->actingAsOwner()
            ->getJson('/api/v1/magazine/wizard/sessions');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertNotContains('Secret Session', $titles);
    }

    public function test_abandoning_a_session_sets_status_but_preserves_rows(): void
    {
        $this->setTenant($this->owner);

        $session = WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'To Abandon',
            'current_step' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()
            ->deleteJson("/api/v1/magazine/wizard/sessions/{$session->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Session abandoned.');

        // Row still exists
        $this->assertDatabaseHas('mag_wizard_sessions', [
            'id' => $session->id,
            'status' => 'abandoned',
            'title' => 'To Abandon',
            'current_step' => 3,
        ]);
    }

    public function test_show_returns_messages_ordered_by_created_at(): void
    {
        $this->setTenant($this->owner);

        $session = WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'With Messages',
            'current_step' => 1,
            'status' => 'active',
        ]);

        // Create messages — insert "second" first to test ordering
        $session->messages()->create([
            'tenant_id' => $this->tenant->id,
            'step' => 1,
            'role' => 'assistant',
            'content' => 'Second message',
            'created_at' => now()->addMinutes(5),
        ]);
        $session->messages()->create([
            'tenant_id' => $this->tenant->id,
            'step' => 1,
            'role' => 'user',
            'content' => 'First message',
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/magazine/wizard/sessions/{$session->id}");

        $response->assertStatus(200);

        $messages = $response->json('data.messages');
        $this->assertCount(2, $messages);
        $this->assertEquals('First message', $messages[0]['content']);
        $this->assertEquals('Second message', $messages[1]['content']);
    }

    public function test_provision_rejects_incomplete_session(): void
    {
        $this->setTenantScope($this->owner);

        $session = WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'current_step' => 1,
            'status' => 'active',
        ]);

        // Not on step 7 — should be rejected
        $this->actingAsOwner()
            ->postJson("/api/v1/magazine/wizard/sessions/{$session->id}/provision")
            ->assertStatus(422);
    }

    public function test_abandoned_sessions_not_listed(): void
    {
        $this->setTenant($this->owner);

        WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'Active One',
            'current_step' => 1,
            'status' => 'active',
        ]);

        WizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'title' => 'Abandoned One',
            'current_step' => 2,
            'status' => 'abandoned',
        ]);

        $response = $this->actingAsOwner()
            ->getJson('/api/v1/magazine/wizard/sessions');

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Active One', $titles);
        $this->assertNotContains('Abandoned One', $titles);
    }
}
