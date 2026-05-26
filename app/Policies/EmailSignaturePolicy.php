<?php

namespace App\Policies;

use App\Models\EmailSignature;
use App\Models\User;

class EmailSignaturePolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, EmailSignature $signature): bool
    {
        return $user->isSuperAdmin()
            ? $signature->user_id === $user->id
            : $signature->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, EmailSignature $signature): bool
    {
        return $user->isSuperAdmin()
            ? $signature->user_id === $user->id
            : $signature->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, EmailSignature $signature): bool
    {
        return $user->isSuperAdmin()
            ? $signature->user_id === $user->id
            : $signature->tenant_id === $user->tenant_id;
    }
}
