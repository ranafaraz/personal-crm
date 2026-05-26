<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // CC/BCC can arrive as comma-separated text from the compose form.
        // Normalise to an array of email strings before validation.
        foreach (['cc', 'bcc'] as $field) {
            $val = $this->input($field);
            if (is_string($val)) {
                $emails = preg_split('/[;,\s]+/', trim($val)) ?: [];
                $emails = array_values(array_filter(array_map('trim', $emails)));
                $this->merge([$field => $emails]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'to_email'         => 'required|email|max:255',
            'to_name'          => 'nullable|string|max:255',
            'subject'          => 'required|string|max:998',
            'body'             => 'required|string',
            'email_account_id' => 'required|integer|exists:email_accounts,id',
            'contact_id'       => 'nullable|integer|exists:contacts,id',
            'opportunity_id'   => 'nullable|integer|exists:opportunities,id',
            'template_id'      => 'nullable|integer|exists:email_templates,id',
            'email_signature_id' => 'nullable|integer|exists:email_signatures,id',
            'cc'               => 'nullable|array',
            'cc.*'             => 'email',
            'bcc'              => 'nullable|array',
            'bcc.*'            => 'email',
            'send_at'          => 'nullable|date|after:now',
            'send_now'         => 'nullable|boolean',
            'send_option'      => 'nullable|in:now,schedule,draft',
            'scheduled_at'     => 'nullable|date|after:now',
            'attachments'      => 'nullable|array',
            'attachments.*'    => 'file|max:20480',
            'schedule_follow_up'    => 'nullable|boolean',
            'follow_up_days'        => 'nullable|integer|min:1|max:60',
            'follow_up_template_id' => 'nullable|integer|exists:email_templates,id',
        ];
    }
}
