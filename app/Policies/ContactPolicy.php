<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin()
            ? $contact->user_id === $user->id
            : $contact->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin()
            ? $contact->user_id === $user->id
            : $contact->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin()
            ? $contact->user_id === $user->id
            : $contact->tenant_id === $user->tenant_id;
    }
}
