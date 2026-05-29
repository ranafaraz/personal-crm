<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SocialPostConfirmation extends Model
{
    protected $table = 'social_post_confirmations';

    protected $fillable = [
        'user_id', 'social_post_id', 'action',
        'content_version_snapshot', 'body_hash',
        'scheduled_at', 'timezone', 'confirmation_token',
        'status', 'approved_by', 'approved_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at'  => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isExpired(): bool  { return $this->expires_at->isPast(); }
    public function isUsable(): bool   { return $this->isApproved() && ! $this->isExpired(); }

    /** Validate that the post body hasn't changed since this confirmation was created. */
    public function contentMatchesPost(SocialPost $post): bool
    {
        return $this->content_version_snapshot === $post->content_version
            && $this->body_hash === hash('sha256', $post->post_body ?? '');
    }

    public static function createFor(SocialPost $post, string $action, ?string $scheduledAt = null, string $timezone = 'Asia/Karachi'): self
    {
        return static::create([
            'user_id'                  => $post->user_id,
            'social_post_id'           => $post->id,
            'action'                   => $action,
            'content_version_snapshot' => $post->content_version,
            'body_hash'                => hash('sha256', $post->post_body ?? ''),
            'scheduled_at'             => $scheduledAt,
            'timezone'                 => $timezone,
            'confirmation_token'       => (string) Str::uuid(),
            'status'                   => 'pending',
            'expires_at'               => now()->addHours(24),
        ]);
    }
}
