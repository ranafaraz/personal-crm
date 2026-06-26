<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => [
                'required', 'string', 'max:500',
                // Title is the unique key per user for opportunities — same
                // contract the CSV importer uses for find-or-create.
                Rule::unique('opportunities', 'title')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)->whereNull('deleted_at')),
            ],
            'type'         => 'nullable|string|max:100',
            'organization' => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:65000',
            'url'          => 'nullable|url|max:2000',
            'status'       => 'nullable|string|max:100',
            'priority'     => 'nullable|in:low,medium,high,urgent',
            'deadline'     => 'nullable|date',
            'notes'        => 'nullable|string|max:65000',
            'contacts'     => 'nullable|array',
            'contacts.*'   => 'integer|exists:contacts,id',
            'tags'         => 'nullable|array',
            'tags.*'       => 'integer|exists:tags,id',
        ];
    }
}
