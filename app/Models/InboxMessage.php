<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class InboxMessage extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email_account_id',
        'uid',
        'message_id',
        'in_reply_to',
        'from_email',
        'from_name',
        'subject',
        'body_text',
        'body_html',
        'received_at',
        'is_read',
        'matched_contact_id',
        'matched_opportunity_id',
        'matched_outbound_id',
        'review_status',
        'sentiment',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'is_read'     => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function matchedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'matched_contact_id');
    }

    public function matchedOpportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'matched_opportunity_id');
    }

    public function matchedOutbound(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'matched_outbound_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('review_status', 'pending')->where('is_read', false);
    }
}
