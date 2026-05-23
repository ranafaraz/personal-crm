<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin()
            ? $template->user_id === $user->id
            : $template->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin()
            ? $template->user_id === $user->id
            : $template->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin()
            ? $template->user_id === $user->id
            : $template->tenant_id === $user->tenant_id;
    }
}
