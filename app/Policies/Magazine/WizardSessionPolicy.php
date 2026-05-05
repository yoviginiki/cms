<?php

namespace App\Policies\Magazine;

use App\Models\Magazine\WizardSession;
use App\Models\User;

class WizardSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WizardSession $session): bool
    {
        return $user->tenant_id === $session->tenant_id
            && $user->id === $session->user_id;
    }

    public function update(User $user, WizardSession $session): bool
    {
        return $user->tenant_id === $session->tenant_id
            && $user->id === $session->user_id;
    }

    public function delete(User $user, WizardSession $session): bool
    {
        return $user->tenant_id === $session->tenant_id
            && $user->id === $session->user_id;
    }
}
