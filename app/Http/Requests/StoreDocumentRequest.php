<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'            => 'required|file|max:20480', // 20 MB
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:65000',
            'document_type'   => 'nullable|string|max:100',
            'opportunity_id'  => 'nullable|integer|exists:opportunities,id',
            'contact_id'      => 'nullable|integer|exists:contacts,id',
        ];
    }
}
