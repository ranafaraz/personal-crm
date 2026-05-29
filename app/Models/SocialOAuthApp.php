<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialOAuthApp extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'provider_key',
        'label', 'client_id', 'client_secret_encrypted',
        'redirect_uri', 'scopes', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'client_secret_encrypted' => 'encrypted',
            'is_default'              => 'boolean',
            'is_active'               => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'social_oauth_app_id');
    }

    /** The redirect URI to register in the LinkedIn Developer App. */
    public function resolvedRedirectUri(): string
    {
        return $this->redirect_uri ?: (config('app.url') . '/social-studio/connections/callback');
    }
}
