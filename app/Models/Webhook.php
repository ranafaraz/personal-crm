<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'url',
        'events',
        'secret',
        'status',
        'last_triggered_at',
        'failure_count',
        'meta',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events'            => 'array',
            'meta'              => 'array',
            'last_triggered_at' => 'datetime',
            'failure_count'     => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
