<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'notification_type',
        'notification_id',
        'channel',
        'status',
        'payload',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
