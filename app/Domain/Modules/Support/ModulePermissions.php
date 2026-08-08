<?php

namespace App\Domain\Modules\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The single place the three module abilities are mapped onto the existing role
 * hierarchy (see docs decision RBAC). No named-permission system is introduced;
 * each ability is a minimum-role threshold checked through the same
 * `User::hasMinimumRole()` the rest of the app uses. Change a threshold here and
 * every gate / route / nav check moves with it.
 */
class ModulePermissions
{
    public const USE = 'module.culture.use';
    public const MANAGE = 'module.culture.manage';
    public const ADMINISTER = 'modules.administer';

    /** ability => minimum role. */
    public const THRESHOLDS = [
        self::USE => 'editor',      // see module UI, view received drafts
        self::MANAGE => 'admin',    // tenant on/off, settings, tokens
        self::ADMINISTER => 'owner', // platform global on/off, all modules
    ];

    /** Register each ability as a Gate backed by the role threshold. */
    public static function registerGates(): void
    {
        foreach (self::THRESHOLDS as $ability => $minRole) {
            Gate::define($ability, fn (?User $user) => $user?->hasMinimumRole($minRole) ?? false);
        }
    }
}
