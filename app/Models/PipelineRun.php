<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRun extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'pipeline_id',
        'status',
        'trigger_source',
        'input',
        'output',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input'       => 'array',
            'output'      => 'array',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
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
}
