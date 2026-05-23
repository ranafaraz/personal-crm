<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailMessage extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email_account_id',
        'contact_id',
        'opportunity_id',
        'template_id',
        'message_id',
        'subject',
        'body',
        'to_email',
        'to_name',
        'cc',
        'bcc',
        'status',
        'direction',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'is_follow_up',
        'follow_up_number',
        'parent_message_id',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'cc'               => 'array',
            'bcc'              => 'array',
            'is_follow_up'     => 'boolean',
            'follow_up_number' => 'integer',
            'scheduled_at'     => 'datetime',
            'sent_at'          => 'datetime',
            'failed_at'        => 'datetime',
            'opened_at'        => 'datetime',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Replies / child messages in the thread.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'parent_message_id');
    }

    /**
     * The parent message this is a reply to.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'parent_message_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
