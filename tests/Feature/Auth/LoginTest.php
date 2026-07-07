<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private function makeUser(string $password = 'secret-password-123'): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'login-user@example.com',
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login-user@example.com',
            'password' => 'secret-password-123',
        ], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('user.email', 'login-user@example.com');
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login-user@example.com',
            'password' => 'wrong-password',
        ], $this->apiHeaders())->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser();

        $last = null;
        for ($i = 0; $i < 7; $i++) {
            $last = $this->postJson('/api/v1/auth/login', [
                'email' => 'login-user@example.com',
                'password' => 'wrong-password',
            ], $this->apiHeaders());
        }

        // After the throttle window is exhausted the login is blocked (429 from
        // the route throttle, or 422 from the request-level rate limiter).
        $this->assertContains($last->getStatusCode(), [429, 422]);
    }

    public function test_login_returns_user_with_tenant(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login-user@example.com',
            'password' => 'secret-password-123',
        ], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('user.tenant.id', $this->tenant->id);
    }

    public function test_can_logout(): void
    {
        $this->actingAsOwner()
            ->postJson('/api/v1/auth/logout', [], $this->apiHeaders())
            ->assertOk();
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $this->actingAsOwner()
            ->getJson('/api/v1/auth/me', $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('user.id', $this->owner->id);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me', $this->apiHeaders())->assertStatus(401);
    }
}
