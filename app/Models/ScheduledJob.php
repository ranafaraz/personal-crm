<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledJob extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'pipeline_id',
        'name',
        'description',
        'job_type',
        'frequency',
        'cron_expression',
        'run_at',
        'status',
        'payload',
        'last_run_at',
        'next_run_at',
        'run_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'meta'        => 'array',
            'run_at'      => 'datetime',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'run_count'   => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
