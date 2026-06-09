<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiDocument extends Model
{
    use SoftDeletes, Tenantable;

    protected $table = 'api_documents';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'document_type',
        'description',
        'is_sensitive',
        'sensitive_warnings',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive'       => 'boolean',
            'sensitive_warnings' => 'array',
        ];
    }

    const DOCUMENT_TYPES = [
        'resume', 'cover_letter', 'proposal', 'portfolio',
        'reference', 'contract', 'report', 'other',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ApiDocumentVersion::class, 'api_document_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ApiDocumentVersion::class, 'current_version_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ApiDocumentLink::class, 'api_document_id');
    }
}
