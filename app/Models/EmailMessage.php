<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmailMessage extends Model
{
    use HasFactory, SoftDeletes, Tenantable, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['to_email', 'subject', 'status', 'direction', 'sent_at', 'scheduled_at', 'failure_reason'])
            ->logOnlyDirty();
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email_account_id',
        'contact_id',
        'opportunity_id',
        'template_id',
        'email_signature_id',
        'rendered_signature',
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
        'clicked_at',
        'open_count',
        'click_count',
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
            'clicked_at'       => 'datetime',
            'open_count'       => 'integer',
            'click_count'      => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getRenderedBodyAttribute(): string
    {
        $body = $this->body ?? '';
        if ($this->bodyLooksLikeMarkdown($body)) {
            return (new \League\CommonMark\CommonMarkConverter())->convert($body)->getContent();
        }
        return $body;
    }

    private function bodyLooksLikeMarkdown(string $body): bool
    {
        // Skip if body is already HTML
        if (preg_match('/<[a-z][\s\S]*>/i', $body)) {
            return false;
        }
        return (bool) preg_match('/\*\*|__|\n[-*] |\n#{1,6} |`/', $body);
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

    public function emailSignature(): BelongsTo
    {
        return $this->belongsTo(EmailSignature::class);
    }

    public function linkClicks(): HasMany
    {
        return $this->hasMany(EmailLinkClick::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function apiAttachments(): BelongsToMany
    {
        return $this->belongsToMany(ApiAttachment::class, 'api_attachment_email_draft', 'email_message_id', 'api_attachment_id')
            ->withPivot('added_by_user_id', 'created_at');
    }

    public function apiDocumentLinks(): HasMany
    {
        return $this->hasMany(ApiDocumentLink::class, 'entity_id')
                    ->where('entity_type', 'email_draft');
    }

    /**
     * Replies / child messages in the thread.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'parent_message_id');
    }

    public function inboxReplies(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'matched_outbound_id');
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
