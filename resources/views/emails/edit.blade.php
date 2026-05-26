@extends('layouts.app')
@section('title', 'Edit ' . ucfirst($email->status))
@section('page-title', 'Edit ' . ucfirst($email->status))

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <style>
        .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: rgb(203 213 225); }
        .ql-container.ql-snow { min-height: 280px; border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
        .ql-toolbar.ql-snow { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; background: rgb(248 250 252); }
    </style>
@endpush

@section('content')
@php
    $contactRecords = $contacts->map(fn ($c) => [
        'id'    => $c->id,
        'email' => $c->email,
        'label' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $c->email,
        'sublabel' => $c->email,
    ])->values();
    $currentAccountId = old('email_account_id', $email->email_account_id);
    $currentScheduledAt = $email->scheduled_at?->format('Y-m-d\TH:i');
    $currentSignatureId = old('email_signature_id', $email->email_signature_id ?: $defaultSignatureId);
    $editorBody = \App\Models\EmailSignature::stripSignatureHtml(old('body', $email->body));
    // Pre-fill CC/BCC from the saved JSON arrays
    $ccSelected  = collect($email->cc  ?? [])->map(fn ($v) => is_array($v) ? ($v['email'] ?? '') : (string) $v)->filter()->values()->all();
    $bccSelected = collect($email->bcc ?? [])->map(fn ($v) => is_array($v) ? ($v['email'] ?? '') : (string) $v)->filter()->values()->all();
@endphp

<div class="max-w-3xl" x-data="composeForm({{ $contactRecords->toJson() }}, @json($signaturePayload), @json((string) $currentSignatureId))">
    <div class="mb-4"><a href="{{ route('emails.show', $email) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to email</a></div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('emails.update', $email) }}" enctype="multipart/form-data" class="space-y-4" @submit="syncBody">
            @csrf
            @method('PUT')
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- From Account --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From Account <span class="text-red-500">*</span></label>
                <select name="email_account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select sending account...</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ $currentAccountId == $account->id ? 'selected' : '' }}>
                            {{ $account->from_name }} &lt;{{ $account->email }}&gt;@if($account->is_default) ★ default @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Link to Contact / Opportunity --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Contact</label>
                    <select name="contact_id" x-model="contactId" @change="onContactSelected" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" data-email="{{ $contact->email }}" data-name="{{ $contact->full_name }}" {{ old('contact_id', $email->contact_id) == $contact->id ? 'selected' : '' }}>
                                {{ $contact->full_name }} ({{ $contact->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Opportunity</label>
                    <select name="opportunity_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($opportunities as $opp)
                            <option value="{{ $opp->id }}" {{ old('opportunity_id', $email->opportunity_id) == $opp->id ? 'selected' : '' }}>
                                {{ Str::limit($opp->title, 60) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- To --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To Email <span class="text-red-500">*</span></label>
                    <input type="email" name="to_email" x-model="toEmail" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="recipient@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To Name</label>
                    <input type="text" name="to_name" x-model="toName" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Recipient Name">
                </div>
            </div>

            {{-- Template (loading auto-fills subject + body via fetch) --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Load Template (overwrites body)</label>
                <select x-model="templateId" @change="loadTemplate" name="template_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— don't load —</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" {{ $email->template_id == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Signature --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Signature</label>
                <select name="email_signature_id" x-model="signatureId" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @if($signatures->isEmpty())
                        <option value="">No signatures available</option>
                    @else
                        <option value="">No signature</option>
                        @foreach($signatures as $signature)
                            <option value="{{ $signature->id }}">
                                {{ $signature->name }}@if($signature->is_default) — default @endif
                            </option>
                        @endforeach
                    @endif
                </select>
                @if($signatures->isEmpty())
                    <p class="text-xs text-slate-400 mt-1"><a href="{{ route('email-signatures.create') }}" class="text-indigo-600 hover:underline">Create a signature</a> to insert it automatically while editing.</p>
                @endif
                <div x-show="signatureId && signatures[signatureId]" x-cloak class="mt-3 border border-slate-200 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <div x-html="signatures[signatureId] ? signatures[signatureId].html : ''"></div>
                </div>
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input id="subject" type="text" name="subject" x-model="subject" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Email subject...">
            </div>

            {{-- Body --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body <span class="text-red-500">*</span></label>
                <div id="composeEditor"></div>
                <textarea name="body" id="composeBody" class="hidden" required>{{ $editorBody }}</textarea>
            </div>

            {{-- CC / BCC chip pickers --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">CC</label>
                    @include('partials._email-chip-picker', [
                        'name'     => 'cc',
                        'contacts' => $contacts,
                        'selected' => $ccSelected,
                    ])
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">BCC</label>
                    @include('partials._email-chip-picker', [
                        'name'     => 'bcc',
                        'contacts' => $contacts,
                        'selected' => $bccSelected,
                    ])
                </div>
            </div>

            {{-- Existing attachments --}}
            @if($email->attachments->isNotEmpty())
                <div>
                    <p class="text-sm font-medium text-slate-700 mb-2">Existing attachments</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($email->attachments as $att)
                            <span class="bg-slate-100 text-slate-700 text-xs px-3 py-1.5 rounded-lg">{{ $att->file_name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Add more attachments --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Add attachments</label>
                <input type="file" name="attachments[]" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                <p class="text-xs text-slate-400 mt-1">Each file ≤ 20 MB. Stored in your Documents library and linked to this email.</p>
            </div>

            {{-- Send Options --}}
            <div class="bg-slate-50 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-700">Save or Send</p>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="draft" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Save as draft (no send yet)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="schedule" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">{{ $email->status === 'scheduled' ? 'Reschedule for' : 'Schedule for later' }}</span>
                    </label>
                    <div x-show="sendOption === 'schedule'" x-cloak class="pl-6">
                        <input type="datetime-local" name="scheduled_at" value="{{ $currentScheduledAt }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="now" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Send now</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    <span x-text="sendOption === 'now' ? 'Send Now' : (sendOption === 'schedule' ? 'Save Schedule' : 'Save Draft')"></span>
                </button>
                <a href="{{ route('emails.show', $email) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@include('partials._email-chip-picker-script')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
let composeQuill = null;

function composeForm(contactsList, signatureList, initialSignatureId) {
    return {
        contacts: contactsList,
        signatures: signatureList,
        contactId: '{{ old('contact_id', $email->contact_id ?? '') }}',
        templateId: '',
        signatureId: initialSignatureId || '',
        subject: @json(old('subject', $email->subject)),
        toEmail: @json(old('to_email', $email->to_email)),
        toName: @json(old('to_name', $email->to_name ?? '')),
        sendOption: '{{ $email->status === 'scheduled' ? 'schedule' : 'draft' }}',
        init() {
            composeQuill = new Quill('#composeEditor', {
                theme: 'snow',
                placeholder: 'Write your email here...',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block', 'link'],
                        [{ align: [] }],
                        ['clean'],
                    ],
                },
            });
            const existing = document.getElementById('composeBody').value;
            if (existing) {
                composeQuill.clipboard.dangerouslyPasteHTML(existing);
            }
        },
        syncBody() {
            const editorHtml = composeQuill.root.innerHTML === '<p><br></p>' ? '' : composeQuill.root.innerHTML;
            const signatureHtml = this.signatureId && this.signatures[this.signatureId] ? this.signatures[this.signatureId].html : '';
            document.getElementById('composeBody').value = editorHtml + signatureHtml;
        },
        onContactSelected() {
            if (!this.contactId) return;
            const c = this.contacts.find(c => c.id == this.contactId);
            if (c && !this.toEmail.trim()) this.toEmail = c.email;
            if (c && !this.toName.trim())  this.toName  = c.label;
        },
        async loadTemplate() {
            if (!this.templateId) return;
            try {
                const res = await fetch('{{ route('emails.get-template') }}?template_id=' + this.templateId, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.subject) this.subject = data.subject;
                if (data.body) {
                    composeQuill.clipboard.dangerouslyPasteHTML(data.body);
                }
            } catch (e) {
                console.warn('template load failed', e);
            }
        },
    };
}
</script>
@endpush
