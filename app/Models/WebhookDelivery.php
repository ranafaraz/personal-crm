<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'webhook_id',
        'event',
        'status',
        'payload',
        'response_code',
        'response_body',
        'attempts',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'       => 'array',
            'response_code' => 'integer',
            'attempts'      => 'integer',
            'delivered_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
