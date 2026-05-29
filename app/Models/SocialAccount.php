<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'provider_id', 'social_oauth_app_id',
        'provider_account_urn', 'display_name',
        'access_token_encrypted', 'refresh_token_encrypted',
        'token_expires_at', 'scopes_json',
        'status', 'last_verified_at', 'metadata_json', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'access_token_encrypted'  => 'encrypted',
            'refresh_token_encrypted' => 'encrypted',
            'token_expires_at'        => 'datetime',
            'last_verified_at'        => 'datetime',
            'scopes_json'             => 'array',
            'metadata_json'           => 'array',
            'is_default'              => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SocialProvider::class, 'provider_id');
    }

    public function oauthApp(): BelongsTo
    {
        return $this->belongsTo(SocialOAuthApp::class, 'social_oauth_app_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class, 'social_account_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    /** Make this the default account; removes default flag from siblings. */
    public function makeDefault(): void
    {
        self::where('user_id', $this->user_id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
