<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'nullable|string|max:255',
            'email'         => [
                'required', 'email', 'max:255',
                // Unique per-user: the same email can exist for different users
                // (multi-tenant) but not twice within one user's contacts.
                Rule::unique('contacts', 'email')->where(fn ($q) => $q->where('user_id', $this->user()->id)->whereNull('deleted_at')),
            ],
            'phone'         => 'nullable|string|max:50',
            'company'       => 'nullable|string|max:255',
            'industry'      => 'nullable|string|max:255',
            'job_title'     => 'nullable|string|max:255',
            'linkedin_url'  => 'nullable|url|max:500',
            'website'       => 'nullable|url|max:500',
            'country'       => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:65000',
            'status'        => 'nullable|in:active,inactive,suppressed',
            'source'        => 'nullable|string|max:100',
            'tags'           => 'nullable|array',
            'tags.*'         => 'integer|exists:tags,id',
            'opportunities'  => 'nullable|array',
            'opportunities.*' => 'integer|exists:opportunities,id',
        ];
    }
}
