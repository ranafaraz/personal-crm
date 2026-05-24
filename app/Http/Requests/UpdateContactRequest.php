<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
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
            'email'         => 'required|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'company'       => 'nullable|string|max:255',
            'industry'      => 'nullable|string|max:255',
            'job_title'     => 'nullable|string|max:255',
            'linkedin_url'  => 'nullable|url|max:500',
            'website'       => 'nullable|url|max:500',
            'country'       => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:10000',
            'status'        => 'nullable|in:active,inactive,suppressed',
            'source'        => 'nullable|string|max:100',
            'tags'          => 'nullable|array',
            'tags.*'        => 'integer|exists:tags,id',
        ];
    }
}
