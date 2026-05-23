<?php

namespace App\Models\Traits;

trait Tenantable
{
    protected static function bootTenantable(): void
    {
        // Auto-set tenant_id when creating a record
        static::creating(function ($model) {
            if (! auth()->check() || isset($model->tenant_id)) {
                return;
            }
            $user = auth()->user();
            if (! $user->isSuperAdmin() && $user->tenant_id) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }

    /** Scope to the current authenticated user's visibility. */
    public function scopeForCurrentUser($query)
    {
        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if ($user->isSuperAdmin()) {
            return $query->where($this->getTable() . '.user_id', $user->id);
        }

        if (! $user->tenant_id) {
            return $query->where($this->getTable() . '.user_id', $user->id);
        }

        return $query->where($this->getTable() . '.tenant_id', $user->tenant_id);
    }
}
