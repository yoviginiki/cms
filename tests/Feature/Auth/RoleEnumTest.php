<?php
namespace Tests\Feature\Auth;
use App\Models\User;
use Tests\TestCase;
class RoleEnumTest extends TestCase
{
    public function test_viewer_and_author_roles_are_valid(): void
    {
        $v = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'viewer']);
        $a = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'author']);
        $this->assertSame('viewer', $v->fresh()->role);
        $this->assertSame('author', $a->fresh()->role);
    }
}
