<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPublishJob extends Model
{
    protected $fillable = [
        'social_post_target_id', 'scheduled_at',
        'job_status', 'attempt_count', 'max_attempts',
        'locked_at', 'next_retry_at',
        'provider_response_sanitized_json',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'                    => 'datetime',
            'locked_at'                       => 'datetime',
            'next_retry_at'                   => 'datetime',
            'provider_response_sanitized_json' => 'array',
            'attempt_count'                   => 'integer',
            'max_attempts'                    => 'integer',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(SocialPostTarget::class, 'social_post_target_id');
    }

    public function isRetryable(): bool
    {
        return $this->job_status === 'failed'
            && $this->attempt_count < $this->max_attempts;
    }
}
