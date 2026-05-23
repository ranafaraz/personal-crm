<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->isSuperAdmin()
            ? $opportunity->user_id === $user->id
            : $opportunity->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->isSuperAdmin()
            ? $opportunity->user_id === $user->id
            : $opportunity->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->isSuperAdmin()
            ? $opportunity->user_id === $user->id
            : $opportunity->tenant_id === $user->tenant_id;
    }
}
