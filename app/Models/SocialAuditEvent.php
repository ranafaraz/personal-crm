<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAuditEvent extends Model
{
    protected $table = 'social_audit_events';

    protected $fillable = [
        'user_id', 'social_account_id', 'social_post_id',
        'event_type', 'event_status',
        'requested_by', 'confirmed_by', 'confirmation_token',
        'safe_request_summary', 'safe_response_summary', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'safe_request_summary'  => 'array',
            'safe_response_summary' => 'array',
        ];
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function account(): BelongsTo { return $this->belongsTo(SocialAccount::class, 'social_account_id'); }
    public function post(): BelongsTo    { return $this->belongsTo(SocialPost::class, 'social_post_id'); }

    public static function log(
        int $userId,
        string $eventType,
        string $eventStatus,
        ?int $accountId = null,
        ?int $postId = null,
        array $requestSummary = [],
        array $responseSummary = [],
        ?string $error = null,
        ?string $confirmationToken = null,
        ?int $confirmedBy = null,
    ): self {
        return static::create([
            'user_id'               => $userId,
            'social_account_id'     => $accountId,
            'social_post_id'        => $postId,
            'event_type'            => $eventType,
            'event_status'          => $eventStatus,
            'requested_by'          => $userId,
            'confirmed_by'          => $confirmedBy,
            'confirmation_token'    => $confirmationToken,
            'safe_request_summary'  => $requestSummary,
            'safe_response_summary' => $responseSummary,
            'error_message'         => $error,
        ]);
    }
}
