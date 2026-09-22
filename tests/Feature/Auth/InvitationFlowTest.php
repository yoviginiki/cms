<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * F10 (audit 2026-09-22) — invite → open → set password → login; the token
 * is hashed at rest, expires, is consumed once; resend/revoke; the users
 * list never returns a token.
 */
class InvitationFlowTest extends TestCase
{
    private function invite(string $email = 'new@example.test'): string
    {
        Notification::fake();
        $res = $this->actingAsOwner()->postJson('/api/v1/users/invite', [
            'email' => $email, 'name' => 'New Person', 'role' => 'editor',
        ], $this->apiHeaders())->assertStatus(201);
        $url = $res->json('data.invite_url');
        $token = basename($url);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        Notification::assertSentTo(User::where('email', $email)->firstOrFail(), UserInvitation::class, fn (UserInvitation $n) => $n->token === $token);

        return $token;
    }

    public function test_full_flow_invite_open_accept_login(): void
    {
        $token = $this->invite();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNotSame($token, $user->invitation_token, 'token must be hashed at rest');

        // open (anonymous)
        $show = $this->getJson("/api/v1/auth/invite/{$token}")->assertOk();
        $this->assertSame('New Person', $show->json('data.name'));
        $this->assertStringNotContainsString('new@example.test', $show->getContent());

        // accept
        $this->postJson("/api/v1/auth/invite/{$token}/accept", ['password' => 'welcome-12345', 'password_confirmation' => 'welcome-12345'])->assertOk();
        $this->assertNull($user->fresh()->invitation_token);

        // login with the new password (fresh guard — the invite() helper acted as the owner)
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'new@example.test', 'password' => 'welcome-12345'], $this->apiHeaders())->assertOk();

        // reused → refused
        $this->postJson("/api/v1/auth/invite/{$token}/accept", ['password' => 'other-12345', 'password_confirmation' => 'other-12345'])->assertStatus(410);
        $this->getJson("/api/v1/auth/invite/{$token}")->assertStatus(410);
    }

    public function test_expired_and_wrong_tokens_are_refused(): void
    {
        $token = $this->invite();
        $this->getJson('/api/v1/auth/invite/' . str_repeat('z', 64))->assertStatus(410);
        $this->getJson('/api/v1/auth/invite/short')->assertStatus(410);

        Carbon::setTestNow(now()->addHours(49));
        $this->getJson("/api/v1/auth/invite/{$token}")->assertStatus(410);
        $this->postJson("/api/v1/auth/invite/{$token}/accept", ['password' => 'welcome-12345', 'password_confirmation' => 'welcome-12345'])->assertStatus(410);
        Carbon::setTestNow(null);
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'new@example.test', 'password' => 'welcome-12345'], $this->apiHeaders())->assertStatus(401);
    }

    public function test_resend_replaces_the_token_and_revoke_removes_the_placeholder(): void
    {
        $old = $this->invite();
        $user = User::where('email', 'new@example.test')->firstOrFail();

        Notification::fake();
        $res = $this->actingAsOwner()->postJson("/api/v1/users/{$user->id}/invite/resend", [], $this->apiHeaders())->assertOk();
        $new = basename($res->json('data.invite_url'));
        $this->assertNotSame($old, $new);
        $this->getJson("/api/v1/auth/invite/{$old}")->assertStatus(410);
        $this->getJson("/api/v1/auth/invite/{$new}")->assertOk();

        $this->actingAsOwner()->deleteJson("/api/v1/users/{$user->id}/invite", [], $this->apiHeaders())->assertStatus(204);
        $this->getJson("/api/v1/auth/invite/{$new}")->assertStatus(410);
        $this->assertNull(User::withTrashed()->find($user->id));

        // the email can be invited again
        $this->invite();
    }

    public function test_users_list_never_exposes_the_token(): void
    {
        $this->invite();
        $list = $this->actingAsOwner()->getJson('/api/v1/users', $this->apiHeaders())->assertOk();
        $this->assertStringNotContainsString('invitation_token', $list->getContent());
        $pending = collect($list->json('data'))->firstWhere('email', 'new@example.test');
        $this->assertSame('pending', $pending['status']);
    }
}
