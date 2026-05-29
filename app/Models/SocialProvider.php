<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialProvider extends Model
{
    protected $fillable = ['key', 'name', 'status', 'capabilities_json'];

    protected function casts(): array
    {
        return [
            'capabilities_json' => 'array',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'provider_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled';
    }
}
