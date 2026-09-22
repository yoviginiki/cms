<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * F09 (audit 2026-09-22) — reset tokens really expire (59/60/61 min), are
 * single-use, the link is really sent, and the answer never reveals whether
 * an account exists.
 */
class PasswordResetTest extends TestCase
{
    private function requestReset(string $email): string
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertOk();
        $token = null;
        Notification::assertSentTo(User::where('email', $email)->firstOrFail(), PasswordResetLink::class, function (PasswordResetLink $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    private function reset(string $email, string $token, string $password = 'new-secret-123')
    {
        return $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    public function test_link_is_sent_for_existing_accounts_only_with_an_identical_answer(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->owner->email])->assertOk()->json('message');
        $r2 = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk();
        Notification::assertSentTo($this->owner, PasswordResetLink::class);
        Notification::assertCount(1);
        $this->assertSame('If an account exists, a reset link has been sent.', $r2->json('message'));
    }

    public function test_token_expires_after_sixty_minutes(): void
    {
        foreach ([59 => 200, 60 => 422, 61 => 422] as $minutes => $expected) {
            Carbon::setTestNow(null);
            $token = $this->requestReset($this->owner->email);
            Carbon::setTestNow(now()->addMinutes($minutes));
            $this->reset($this->owner->email, $token)->assertStatus($expected);
            Carbon::setTestNow(null);
        }
    }

    public function test_token_is_single_use(): void
    {
        $token = $this->requestReset($this->owner->email);
        $this->reset($this->owner->email, $token)->assertOk();
        $this->assertTrue(Hash::check('new-secret-123', $this->owner->fresh()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $this->owner->email)->count());

        // second use of the same token → refused, password unchanged
        $this->reset($this->owner->email, $token, 'another-pass-456')->assertStatus(422);
        $this->assertTrue(Hash::check('new-secret-123', $this->owner->fresh()->password));
    }

    public function test_wrong_token_and_other_users_token_are_refused(): void
    {
        $other = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        $token = $this->requestReset($other->email);
        $this->reset($this->owner->email, $token)->assertStatus(422);
        $this->reset($this->owner->email, str_repeat('x', 64))->assertStatus(422);
        $this->reset($other->email, $token)->assertOk();
    }
}
