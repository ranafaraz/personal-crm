<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Document $document): bool
    {
        return $user->isSuperAdmin()
            ? $document->user_id === $user->id
            : $document->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Document $document): bool
    {
        return $user->isSuperAdmin()
            ? $document->user_id === $user->id
            : $document->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isSuperAdmin()
            ? $document->user_id === $user->id
            : $document->tenant_id === $user->tenant_id;
    }
}
