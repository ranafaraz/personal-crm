<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialActivityLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'event',
        'subject_type', 'subject_id',
        'description', 'metadata_json',
        'ip_address', 'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'happened_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        int $userId,
        int $tenantId,
        string $event,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $description = null,
        array $metadata = [],
        ?string $ip = null,
    ): self {
        return static::create([
            'tenant_id'     => $tenantId,
            'user_id'       => $userId,
            'event'         => $event,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'description'   => $description,
            'metadata_json' => $metadata ?: null,
            'ip_address'    => $ip,
            'happened_at'   => now(),
        ]);
    }
}
