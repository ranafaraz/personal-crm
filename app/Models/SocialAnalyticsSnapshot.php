<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAnalyticsSnapshot extends Model
{
    protected $table = 'social_analytics_snapshots';

    protected $fillable = [
        'social_account_id', 'social_post_id',
        'analytics_scope', 'metric_name', 'metric_value',
        'aggregation', 'date_range_start', 'date_range_end',
        'collected_at', 'raw_provider_response',
    ];

    protected function casts(): array
    {
        return [
            'metric_value'         => 'integer',
            'date_range_start'     => 'date',
            'date_range_end'       => 'date',
            'collected_at'         => 'datetime',
            'raw_provider_response'=> 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
