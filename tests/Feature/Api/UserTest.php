<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_admin_can_list_users(): void
    {
        User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->getJson('/api/v1/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_editor_cannot_list_users(): void
    {
        $this->actingAsEditor()
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_admin_can_invite_user(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/v1/users/invite', [
                'name' => 'New Person',
                'email' => 'new@example.com',
                'role' => 'editor',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['user', 'invite_url']]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_invite_fails_for_duplicate_email(): void
    {
        User::factory()->editor()->create(['tenant_id' => $this->tenant->id, 'email' => 'dup@example.com']);

        $this->actingAsAdmin()
            ->postJson('/api/v1/users/invite', [
                'name' => 'Dup',
                'email' => 'dup@example.com',
                'role' => 'editor',
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_update_user_role(): void
    {
        $user = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->putJson("/api/v1/users/{$user->id}/role", ['role' => 'editor'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'editor']);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_editor_cannot_invite_user(): void
    {
        $this->actingAsEditor()
            ->postJson('/api/v1/users/invite', [
                'name' => 'Someone',
                'email' => 'someone@example.com',
                'role' => 'editor',
            ])
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')
            ->assertStatus(401);
    }
}
