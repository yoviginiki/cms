<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit reconciliation — the DB `users_role_check` only permitted
 * owner/admin/editor, but the app's role hierarchy (User::hasMinimumRole) and
 * the invite endpoint offer `viewer` and `author` too, so inviting one would
 * hit a check-constraint violation. Widen the constraint to the full set.
 */
return new class extends Migration
{
    private array $roles = ['owner', 'admin', 'editor', 'author', 'viewer'];

    public function up(): void
    {
        $this->setAllowedRoles($this->roles);
    }

    public function down(): void
    {
        // Only narrow back if no rows would violate it.
        $inUse = DB::table('users')->whereIn('role', ['author', 'viewer'])->exists();
        if (!$inUse) {
            $this->setAllowedRoles(['owner', 'admin', 'editor']);
        }
    }

    private function setAllowedRoles(array $roles): void
    {
        $list = implode(', ', array_map(fn ($r) => "'{$r}'", $roles));
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY[{$list}]::text[]))");
    }
};
