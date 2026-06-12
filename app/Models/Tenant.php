<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Paddle\Billable;

class Tenant extends Model
{
    use Billable;
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'email', 'plan', 'status',
        'max_users', 'trial_ends_at', 'notes',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'admin');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function trialExpired(): bool
    {
        return $this->isTrial()
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    /** Customer name sent to Paddle when a checkout is created. */
    public function paddleName(): ?string
    {
        return $this->name;
    }

    /** Customer email sent to Paddle; falls back to the first admin's email. */
    public function paddleEmail(): ?string
    {
        return $this->email ?: $this->admins()->value('email');
    }

    public function planLabel(): string
    {
        return config("plans.plans.{$this->plan}.label", 'Free');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'active'    => 'bg-green-100 text-green-800',
            'trial'     => 'bg-yellow-100 text-yellow-800',
            'suspended' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-600',
            default     => 'bg-gray-100 text-gray-600',
        };
    }
}
