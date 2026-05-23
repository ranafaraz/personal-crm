<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimelineEvent extends Model
{
    use Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'timelineable_id',
        'timelineable_type',
        'event_type',
        'description',
        'metadata',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'    => 'array',
            'happened_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timelineable(): MorphTo
    {
        return $this->morphTo();
    }
}
