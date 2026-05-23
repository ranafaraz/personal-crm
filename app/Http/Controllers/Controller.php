<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Returns a query scoped to the current user's tenant (or user_id for super_admin).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function tenantQuery(string $model): Builder
    {
        return $model::forCurrentUser();
    }

    /**
     * Injects user_id + tenant_id into data before create/update.
     */
    protected function tenantData(array $data): array
    {
        $user = auth()->user();
        $data['user_id'] = $user->id;
        if (! $user->isSuperAdmin() && $user->tenant_id) {
            $data['tenant_id'] = $user->tenant_id;
        }

        return $data;
    }

    /**
     * Returns a where-clause array for the current user's scope.
     */
    protected function tenantScope(): array
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return ['user_id' => $user->id];
        }

        if (! $user->tenant_id) {
            return ['user_id' => $user->id];
        }

        return ['tenant_id' => $user->tenant_id];
    }
}
