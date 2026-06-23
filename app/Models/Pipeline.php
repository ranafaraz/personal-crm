<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'trigger_type',
        'status',
        'steps',
        'config',
        'last_run_at',
        'run_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'steps'       => 'array',
            'config'      => 'array',
            'meta'        => 'array',
            'last_run_at' => 'datetime',
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

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
