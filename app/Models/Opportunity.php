<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'type',
        'organization',
        'description',
        'url',
        'status',
        'priority',
        'deadline',
        'notes',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline'         => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'opportunity_contact')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function timelineEvents(): MorphMany
    {
        return $this->morphMany(TimelineEvent::class, 'timelineable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'waiting_reply', 'replied', 'interview']);
    }

    /**
     * Opportunities with no activity in the last $days days.
     */
    public function scopeStale($query, int $days = 14)
    {
        return $query->where(function ($q) use ($days) {
            $threshold = Carbon::now()->subDays($days);
            $q->where('last_activity_at', '<', $threshold)
              ->orWhereNull('last_activity_at');
        });
    }

    /**
     * Opportunities whose deadline falls within the next $days days.
     */
    public function scopeDeadlineSoon($query, int $days = 7)
    {
        return $query->whereNotNull('deadline')
                     ->whereBetween('deadline', [Carbon::today(), Carbon::today()->addDays($days)]);
    }
}
