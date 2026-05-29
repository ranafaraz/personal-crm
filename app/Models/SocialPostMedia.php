<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SocialPostMedia extends Pivot
{
    protected $table = 'social_post_media';

    protected $fillable = [
        'social_post_id', 'social_media_asset_id',
        'display_order', 'is_featured', 'alt_text_override',
    ];

    protected function casts(): array
    {
        return [
            'is_featured'   => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAsset::class, 'social_media_asset_id');
    }
}
