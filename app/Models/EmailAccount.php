<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'email',
        'from_name',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'daily_limit',
        'hourly_limit',
        'min_delay_seconds',
        'emails_sent_today',
        'last_reset_at',
        'last_sync_at',
        'sync_status',
        'is_active',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'smtp_password'    => 'encrypted',
            'imap_password'    => 'encrypted',
            'last_reset_at'    => 'datetime',
            'last_sync_at'     => 'datetime',
            'is_active'        => 'boolean',
            'is_default'       => 'boolean',
            'smtp_port'        => 'integer',
            'imap_port'        => 'integer',
            'daily_limit'      => 'integer',
            'hourly_limit'     => 'integer',
            'min_delay_seconds' => 'integer',
            'emails_sent_today' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function inboxMessages(): HasMany
    {
        return $this->hasMany(InboxMessage::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Percentage of the daily send limit already consumed (0–100).
     */
    public function getDailyUsagePercentAttribute(): float
    {
        if ($this->daily_limit === 0) {
            return 0.0;
        }

        return round(($this->emails_sent_today / $this->daily_limit) * 100, 1);
    }
}
