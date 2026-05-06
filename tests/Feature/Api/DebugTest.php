<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DebugTest extends TestCase
{
    public function test_admin_can_access_debug(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->getJson('/api/v1/debug')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['health', 'system', 'failed_jobs', 'content_issues', 'recent_errors']]);
    }

    public function test_editor_cannot_access_debug(): void
    {
        $this->actingAsEditor()
            ->getJson('/api/v1/debug')
            ->assertStatus(403);
    }

    public function test_admin_can_get_logs(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->getJson('/api/v1/debug/logs')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['entries', 'stats']]);
    }

    public function test_admin_can_clear_logs(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->deleteJson('/api/v1/debug/logs')
            ->assertStatus(200);
    }

    public function test_admin_can_retry_failed_jobs(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->postJson('/api/v1/debug/retry-failed')
            ->assertStatus(200);
    }

    public function test_admin_can_flush_failed_jobs(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->postJson('/api/v1/debug/flush-failed')
            ->assertStatus(200);
    }

    public function test_admin_can_clear_cache(): void
    {
        $this->setTenantScope($this->owner);

        $this->actingAsAdmin()
            ->postJson('/api/v1/debug/clear-cache', ['type' => 'cache'])
            ->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_debug(): void
    {
        $this->getJson('/api/v1/debug')
            ->assertStatus(401);
    }
}
