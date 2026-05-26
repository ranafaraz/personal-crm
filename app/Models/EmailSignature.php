<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EmailSignature extends Model
{
    use SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'body',
        'image_path',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function renderHtml(): string
    {
        $body = trim((string) $this->body);
        $image = $this->image_url
            ? '<div><img src="' . e($this->image_url) . '" alt="' . e($this->name) . '" style="max-width:220px;height:auto;"></div>'
            : '';

        return '<div><br></div><div data-email-signature="1" data-email-signature-id="' . e((string) $this->id) . '">' . $body . $image . '</div>';
    }

    public static function stripSignatureHtml(?string $html): string
    {
        return preg_replace('/\s*<div><br><\/div><div data-email-signature="1"[^>]*>.*$/s', '', (string) $html) ?? (string) $html;
    }
}
