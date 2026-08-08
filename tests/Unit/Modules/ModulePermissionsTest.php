<?php

namespace Tests\Unit\Modules;

use App\Domain\Modules\Support\ModulePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The three module abilities map onto the role hierarchy
 * (viewer<author<editor<admin<owner). See docs decision RBAC.
 */
class ModulePermissionsTest extends TestCase
{
    private function user(string $role): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => $role,
        ]);
    }

    public function test_use_requires_editor_or_above(): void
    {
        $this->assertFalse(Gate::forUser($this->user('author'))->allows(ModulePermissions::USE));
        $this->assertTrue(Gate::forUser($this->user('editor'))->allows(ModulePermissions::USE));
        $this->assertTrue(Gate::forUser($this->user('owner'))->allows(ModulePermissions::USE));
    }

    public function test_manage_requires_admin_or_above(): void
    {
        $this->assertFalse(Gate::forUser($this->user('editor'))->allows(ModulePermissions::MANAGE));
        $this->assertTrue(Gate::forUser($this->user('admin'))->allows(ModulePermissions::MANAGE));
        $this->assertTrue(Gate::forUser($this->user('owner'))->allows(ModulePermissions::MANAGE));
    }

    public function test_administer_requires_owner(): void
    {
        $this->assertFalse(Gate::forUser($this->user('admin'))->allows(ModulePermissions::ADMINISTER));
        $this->assertTrue(Gate::forUser($this->user('owner'))->allows(ModulePermissions::ADMINISTER));
    }
}
