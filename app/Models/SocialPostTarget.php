<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SocialPostTarget extends Model
{
    protected $fillable = [
        'social_post_id', 'social_account_id', 'provider_key',
        'platform_body', 'platform_metadata_json',
        'status', 'scheduled_at',
        'remote_post_id', 'remote_post_url', 'published_at',
        'error_code', 'error_message', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'platform_metadata_json' => 'array',
            'scheduled_at'           => 'datetime',
            'published_at'           => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $target) {
            if (empty($target->idempotency_key)) {
                $target->idempotency_key = hash('sha256', implode('|', [
                    $target->social_post_id,
                    $target->social_account_id,
                    $target->provider_key,
                    Str::random(16),
                ]));
            }
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function publishJobs(): HasMany
    {
        return $this->hasMany(SocialPublishJob::class, 'social_post_target_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
