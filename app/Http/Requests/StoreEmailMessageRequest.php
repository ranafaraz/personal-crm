<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_email'         => 'required|email|max:255',
            'to_name'          => 'nullable|string|max:255',
            'subject'          => 'required|string|max:998',
            'body'             => 'required|string',
            'email_account_id' => ['required', 'integer', Rule::exists('email_accounts', 'id')->where('user_id', auth()->id())],
            'contact_id'       => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('user_id', auth()->id())],
            'opportunity_id'   => ['nullable', 'integer', Rule::exists('opportunities', 'id')->where('user_id', auth()->id())],
            'template_id'      => ['nullable', 'integer', Rule::exists('email_templates', 'id')->where('user_id', auth()->id())],
            'cc'               => 'nullable|array',
            'cc.*'             => 'email',
            'bcc'              => 'nullable|array',
            'bcc.*'            => 'email',
            'send_at'          => 'nullable|date|after:now',
            'send_now'         => 'nullable|boolean',
        ];
    }
}
