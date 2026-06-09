<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'opportunity_id',
        'contact_id',
        'name',
        'description',
        'public_url',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'document_type',
    ];

    const DOCUMENT_TYPES = ['resume', 'cover_letter', 'proposal', 'portfolio', 'reference', 'other'];

    const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
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

    public function emailAttachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Human-readable file size (e.g. "1.2 MB").
     */
    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1_073_741_824) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        return round($bytes / 1_073_741_824, 2) . ' GB';
    }
}
