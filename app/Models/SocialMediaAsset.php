<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SocialMediaAsset extends Model
{
    use SoftDeletes;

    const SENSITIVE_PATTERNS = ['passport', 'cnic', 'nic', 'national_id', 'transcript', 'degree', 'diploma', 'id_card'];

    protected $fillable = [
        'tenant_id', 'user_id', 'filename', 'mime_type', 'size_bytes',
        'sha256_hash', 'storage_path', 'thumbnail_path', 'alt_text',
        'caption_or_prompt_note', 'rights_status', 'approval_status',
        'linkedin_media_urn', 'linkedin_upload_status',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(SocialPost::class, 'social_post_media', 'social_media_asset_id', 'social_post_id')
            ->withPivot(['display_order', 'is_featured', 'alt_text_override'])
            ->withTimestamps();
    }

    public function storageUrl(): string
    {
        return Storage::disk('public')->url($this->storage_path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path
            ? Storage::disk('public')->url($this->thumbnail_path)
            : null;
    }

    public function isSensitive(): bool
    {
        $lower = strtolower($this->filename);
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
