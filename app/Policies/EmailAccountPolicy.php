<?php

namespace App\Policies;

use App\Models\EmailAccount;
use App\Models\User;

class EmailAccountPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, EmailAccount $account): bool
    {
        return $user->isSuperAdmin()
            ? $account->user_id === $user->id
            : $account->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, EmailAccount $account): bool
    {
        return $user->isSuperAdmin()
            ? $account->user_id === $user->id
            : $account->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, EmailAccount $account): bool
    {
        return $user->isSuperAdmin()
            ? $account->user_id === $user->id
            : $account->tenant_id === $user->tenant_id;
    }
}
