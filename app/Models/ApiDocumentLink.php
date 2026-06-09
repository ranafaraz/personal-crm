<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiDocumentLink extends Model
{
    protected $table = 'api_document_links';

    // Links are append-only — no updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'api_document_id',
        'entity_type',
        'entity_id',
        'linked_by_api_client_id',
    ];

    const ENTITY_TYPES = ['opportunity', 'contact', 'email_draft', 'follow_up'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApiDocument::class, 'api_document_id');
    }
}
