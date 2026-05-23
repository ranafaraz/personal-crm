<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'subject',
        'body',
        'type',
        'variables',
        'is_active',
        'times_used',
    ];

    protected function casts(): array
    {
        return [
            'variables'  => 'array',
            'is_active'  => 'boolean',
            'times_used' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'template_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'email_template_id');
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * Parse all {{variable}} placeholders from the subject and body.
     *
     * @return array<int, string>
     */
    public function extractVariables(): array
    {
        $pattern = '/\{\{(\w+)\}\}/';
        $matches = [];

        preg_match_all($pattern, $this->subject . ' ' . $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
