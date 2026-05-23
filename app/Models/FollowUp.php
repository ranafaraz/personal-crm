<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FollowUp extends Model
{
    use Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'opportunity_id',
        'contact_id',
        'email_account_id',
        'email_template_id',
        'email_message_id',
        'follow_up_number',
        'due_at',
        'sent_at',
        'status',
        'cancel_reason',
        'subject',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'due_at'           => 'datetime',
            'sent_at'          => 'datetime',
            'follow_up_number' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', 'pending')
                     ->whereDate('due_at', Carbon::today());
    }
}
