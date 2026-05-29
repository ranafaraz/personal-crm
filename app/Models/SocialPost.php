<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'title_internal', 'topic', 'post_body',
        'platform_variant_json', 'post_type', 'article_url',
        'source_notes', 'source_links_json', 'hashtags_json', 'call_to_action',
        'status', 'approval_status', 'approved_at', 'approved_by',
        'scheduled_at', 'timezone_display', 'created_source',
    ];

    protected function casts(): array
    {
        return [
            'platform_variant_json' => 'array',
            'source_links_json'     => 'array',
            'hashtags_json'         => 'array',
            'approved_at'           => 'datetime',
            'scheduled_at'          => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class, 'social_post_id');
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(SocialMediaAsset::class, 'social_post_media', 'social_post_id', 'social_media_asset_id')
            ->withPivot(['display_order', 'is_featured', 'alt_text_override'])
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(SocialActivityLog::class, 'subject_id')
            ->where('subject_type', self::class);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['idea', 'draft', 'ready_for_review', 'failed']);
    }

    public function hashtags(): array
    {
        return $this->hashtags_json ?? [];
    }

    public function hashtagString(): string
    {
        return collect($this->hashtags())->map(fn ($h) => '#' . ltrim($h, '#'))->implode(' ');
    }
}
