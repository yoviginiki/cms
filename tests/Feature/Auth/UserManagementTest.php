<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

/**
 * FIX-A2b — user-management privilege-escalation guards.
 */
class UserManagementTest extends TestCase
{
    public function test_non_owner_admin_cannot_invite_an_admin(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/users/invite', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'role' => 'admin',
        ], $this->apiHeaders());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'newadmin@example.com']);
    }

    public function test_owner_can_invite_an_admin(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/v1/users/invite', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'role' => 'admin',
        ], $this->apiHeaders());

        $response->assertStatus(201);
    }

    public function test_admin_cannot_demote_the_owner(): void
    {
        $target = $this->owner; // the tenant owner
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/users/{$target->id}/role", [
            'role' => 'editor',
        ], $this->apiHeaders());

        $response->assertStatus(403);
        $this->assertSame('owner', $target->fresh()->role);
    }
}
