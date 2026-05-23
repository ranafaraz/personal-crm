<?php

namespace App\Policies;

use App\Models\EmailMessage;
use App\Models\User;

class EmailMessagePolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, EmailMessage $message): bool
    {
        return $user->isSuperAdmin()
            ? $message->user_id === $user->id
            : $message->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, EmailMessage $message): bool
    {
        return $user->isSuperAdmin()
            ? $message->user_id === $user->id
            : $message->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, EmailMessage $message): bool
    {
        return $user->isSuperAdmin()
            ? $message->user_id === $user->id
            : $message->tenant_id === $user->tenant_id;
    }
}
