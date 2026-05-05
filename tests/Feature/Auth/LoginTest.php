<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_can_login_with_valid_credentials(): void
    {
        $this->markTestIncomplete();
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        $this->markTestIncomplete();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->markTestIncomplete();
    }

    public function test_login_returns_user_with_tenant(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_logout(): void
    {
        $this->markTestIncomplete();
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $this->markTestIncomplete();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->markTestIncomplete();
    }
}
