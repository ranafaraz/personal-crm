<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressionList extends Model
{
    use HasFactory, Tenantable;

    protected $table = 'suppression_list';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email',
        'reason',
        'notes',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether an email address is on the suppression list for a given user.
     */
    public static function isSuppressed(int $userId, string $email): bool
    {
        $user = User::find($userId);

        $query = static::query()->where('email', strtolower(trim($email)));

        if ($user && ! $user->isSuperAdmin() && $user->tenant_id) {
            return $query->where('tenant_id', $user->tenant_id)->exists();
        }

        return $query->where('user_id', $userId)->exists();
    }
}
