<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactImport extends Model
{
    use Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'file_name',
        'file_path',
        'total_rows',
        'processed_rows',
        'imported_rows',
        'failed_rows',
        'skipped_rows',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'total_rows'     => 'integer',
            'processed_rows' => 'integer',
            'imported_rows'  => 'integer',
            'failed_rows'    => 'integer',
            'skipped_rows'   => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ContactImportRow::class);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Processing progress as a percentage (0–100).
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0.0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }
}
