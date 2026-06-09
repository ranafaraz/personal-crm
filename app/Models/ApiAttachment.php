<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiAttachment extends Model
{
    use SoftDeletes, Tenantable;

    protected $table = 'api_attachments';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'added_by_api_client_id',
        'filename',
        'public_url',
        'file_path',
        'storage_disk',
        'mime_type',
        'size_bytes',
        'category',
        'notes',
        'validation_status',
        'validation_warnings',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'           => 'integer',
            'validation_warnings'  => 'array',
        ];
    }

    public function hasLocalFile(): bool
    {
        return !empty($this->file_path);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'added_by_api_client_id');
    }

    public function emailDrafts(): BelongsToMany
    {
        return $this->belongsToMany(EmailMessage::class, 'api_attachment_email_draft', 'api_attachment_id', 'email_message_id')
            ->withPivot('added_by_user_id')
            ->withTimestamps();
    }

    public function followUps(): BelongsToMany
    {
        return $this->belongsToMany(FollowUp::class, 'api_attachment_follow_up', 'api_attachment_id', 'follow_up_id')
            ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

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

    const CATEGORIES = [
        'cv_resume', 'cover_letter', 'portfolio', 'transcript',
        'certificate', 'id_document', 'reference', 'sample_work', 'proposal', 'other',
    ];

    // Filenames containing these patterns trigger a "cold outreach" warning
    const SENSITIVE_FILENAME_PATTERNS = [
        'passport', 'cnic', 'nic', 'national_id', 'national-id',
        'id_card', 'id-card', 'transcript', 'degree', 'diploma', 'certificate',
    ];

    // -------------------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------------------

    public static function validateUrl(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'URL is not valid.';
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Only http:// and https:// URLs are allowed.';
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return 'Local URLs are not allowed.';
        }

        // Block RFC-1918 private ranges
        if (
            preg_match('/^192\.168\./', $host) ||
            preg_match('/^10\./', $host) ||
            preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)
        ) {
            return 'Private network URLs are not allowed.';
        }

        return null; // valid
    }

    public static function detectSensitiveWarnings(string $filename, string $category): array
    {
        $warnings = [];
        $lower    = strtolower($filename);

        foreach (self::SENSITIVE_FILENAME_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $warnings[] = "Filename suggests identity or academic credentials ({$pattern}). Ensure recipient consent before attaching to cold outreach.";
                break;
            }
        }

        if (in_array($category, ['transcript', 'certificate', 'id_document'], true)) {
            $warnings[] = "Category '{$category}' contains sensitive personal documents. Only attach to outreach where the recipient explicitly requested these.";
        }

        return $warnings;
    }
}
