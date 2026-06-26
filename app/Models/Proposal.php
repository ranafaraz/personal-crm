<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'contact_id',
        'opportunity_id',
        'title',
        'version',
        'status',
        'amount',
        'currency',
        'body',
        'url',
        'valid_until',
        'sent_at',
        'responded_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'valid_until'  => 'date',
            'sent_at'      => 'datetime',
            'responded_at' => 'datetime',
            'meta'         => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
