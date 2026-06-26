<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|max:255',
            'from_name'         => 'required|string|max:255',
            'smtp_host'         => 'required|string|max:255',
            'smtp_port'         => 'required|integer|between:1,65535',
            'smtp_encryption'   => 'required|in:tls,ssl,none',
            'smtp_username'     => 'required|string|max:255',
            // Password is optional on update – only written if provided
            'smtp_password'     => 'nullable|string|max:1000',
            'imap_host'         => 'nullable|string|max:255',
            'imap_port'         => 'nullable|integer|between:1,65535',
            'imap_encryption'   => 'nullable|in:tls,ssl,none',
            'imap_username'     => 'nullable|string|max:255',
            'imap_password'     => 'nullable|string|max:1000',
            'daily_limit'       => 'nullable|integer|min:1|max:1000',
            'hourly_limit'      => 'nullable|integer|min:1|max:200',
            'min_delay_seconds' => 'nullable|integer|min:0|max:3600',
            'is_active'         => 'boolean',
            'is_default'        => 'boolean',
            'notes'             => 'nullable|string|max:65000',
        ];
    }
}
