@extends('layouts.app')
@section('title', 'Compose Email')
@section('page-title', 'Compose Email')

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
    $defaultAccountId = old('email_account_id')
        ?: request('account_id')
        ?: optional($emailAccounts->firstWhere('is_default', true))->id;
    $selectedSignatureId = old('email_signature_id', $defaultSignatureId);
    $editorBody = \App\Models\EmailSignature::stripSignatureHtml(old('body', ''));
@endphp

<div class="max-w-3xl" x-data="composeForm({{ $contactRecords->toJson() }}, @json($signaturePayload), @json((string) $selectedSignatureId))">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('emails.store') }}" enctype="multipart/form-data" class="space-y-4" @submit="syncBody">
            @csrf
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
                        <option value="{{ $account->id }}" {{ $defaultAccountId == $account->id ? 'selected' : '' }}>
                            {{ $account->from_name }} &lt;{{ $account->email }}&gt;@if($account->is_default) ★ default @endif
                        </option>
                    @endforeach
                </select>
                @if($emailAccounts->isEmpty())
                    <p class="text-xs text-red-500 mt-1">No email accounts configured. <a href="{{ route('email-accounts.create') }}" class="underline">Add one first</a>.</p>
                @endif
            </div>

            {{-- Link to Contact / Opportunity (sets To when contact picked) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Contact</label>
                    <select name="contact_id" x-model="contactId" @change="onContactSelected" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" data-email="{{ $contact->email }}" data-name="{{ $contact->full_name }}" {{ (old('contact_id') == $contact->id || request('contact_id') == $contact->id) ? 'selected' : '' }}>
                                {{ $contact->full_name }} ({{ $contact->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Selecting a contact auto-fills the To Email / To Name fields.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Opportunity</label>
                    <select name="opportunity_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($opportunities as $opp)
                            <option value="{{ $opp->id }}" {{ (old('opportunity_id') == $opp->id || request('opportunity_id') == $opp->id) ? 'selected' : '' }}>
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Load Template</label>
                <select x-model="templateId" @change="loadTemplate" name="template_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select template (optional)...</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Loading a template overwrites the subject and body.</p>
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
                    <p class="text-xs text-slate-400 mt-1"><a href="{{ route('email-signatures.create') }}" class="text-indigo-600 hover:underline">Create a signature</a> to insert it automatically while composing.</p>
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

            {{-- Body (Quill rich text editor; hidden input is what posts) --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body <span class="text-red-500">*</span></label>
                <div id="composeEditor"></div>
                <textarea name="body" id="composeBody" class="hidden" required>{{ $editorBody }}</textarea>
            </div>

            {{-- CC / BCC (multi-select chip pickers; suggests contacts + accepts free emails) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">CC</label>
                    @include('partials._email-chip-picker', [
                        'name'     => 'cc',
                        'contacts' => $contacts,
                        'selected' => old('cc'),
                    ])
                    <p class="text-xs text-slate-400 mt-1">Pick a contact or type any email and press Enter.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">BCC</label>
                    @include('partials._email-chip-picker', [
                        'name'     => 'bcc',
                        'contacts' => $contacts,
                        'selected' => old('bcc'),
                    ])
                    <p class="text-xs text-slate-400 mt-1">Pick a contact or type any email and press Enter.</p>
                </div>
            </div>

            {{-- Attachments --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Attachments</label>
                <input type="file" name="attachments[]" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                <p class="text-xs text-slate-400 mt-1">Each file ≤ 20 MB. Uploads are saved into your Documents library and linked to this email + opportunity.</p>
            </div>

            {{-- Send Options --}}
            <div class="bg-slate-50 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-700">Send Options</p>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="now" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Send Now</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="schedule" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Schedule For Later</span>
                    </label>
                    <div x-show="sendOption === 'schedule'" x-cloak class="pl-6">
                        <input type="datetime-local" name="scheduled_at" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="draft" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Save as Draft</span>
                    </label>
                </div>
            </div>

            {{-- Follow-up: now with template selection --}}
            <div x-data="{ followUp: false }" class="bg-slate-50 rounded-xl p-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="schedule_follow_up" x-model="followUp" value="1" class="text-indigo-600">
                    <span class="text-sm font-medium text-slate-700">Schedule follow-up if no reply received</span>
                </label>
                <div x-show="followUp" x-cloak class="mt-3 space-y-3">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-slate-600">After</label>
                        <input type="number" name="follow_up_days" value="5" min="1" max="60" class="w-16 px-2 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <label class="text-sm text-slate-600">days, send template:</label>
                    </div>
                    <select name="follow_up_template_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— Use opportunity default / blank reminder —</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @if($template->type === 'follow_up') selected @endif>
                                {{ $template->name }} ({{ ucwords(str_replace('_', ' ', $template->type)) }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400">The selected template will be sent automatically on the due date unless a reply has arrived in the meantime.</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    <span x-text="sendOption === 'now' ? 'Send Now' : (sendOption === 'schedule' ? 'Schedule Email' : 'Save Draft')"></span>
                </button>
                <a href="{{ route('emails.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
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
        contactId: '{{ old('contact_id', request('contact_id', '')) }}',
        templateId: '',
        signatureId: initialSignatureId || '',
        subject: @json(old('subject', '')),
        toEmail: @json(old('to_email', '')),
        toName: @json(old('to_name', '')),
        sendOption: 'now',
        init() {
            // Bootstrap Quill on the placeholder div and seed it with any old() body
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
            const oldBody = document.getElementById('composeBody').value;
            if (oldBody) {
                composeQuill.clipboard.dangerouslyPasteHTML(oldBody);
            }
            // If contact was already selected via query string, populate To
            if (this.contactId) {
                this.onContactSelected();
            }
        },
        syncBody() {
            // Right before form submit, copy Quill's HTML into the hidden textarea
            const editorHtml = composeQuill.root.innerHTML === '<p><br></p>' ? '' : composeQuill.root.innerHTML;
            const signatureHtml = this.signatureId && this.signatures[this.signatureId] ? this.signatures[this.signatureId].html : '';
            document.getElementById('composeBody').value = editorHtml + signatureHtml;
        },
        onContactSelected() {
            if (!this.contactId) return;
            const c = this.contacts.find(c => c.id == this.contactId);
            if (c) {
                if (!this.toEmail) this.toEmail = c.email;
                if (!this.toName)  this.toName  = c.label;
                // If user has explicit values, keep them — only fill blanks
                if (this.toEmail !== c.email && !this.toEmail.trim()) this.toEmail = c.email;
            }
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
