@extends('layouts.app')
@section('title', $opportunity->title)
@section('page-title', Str::limit($opportunity->title, 60))
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Opportunities', 'url' => route('opportunities.index')],
        ['label' => Str::limit($opportunity->title, 40)],
    ]" />
@endsection
@section('content')
@php
    $typeColors = ['job'=>'blue','scholarship'=>'purple','research'=>'indigo','grant'=>'yellow','networking'=>'green'];
    $statusColors = ['draft'=>'slate','active'=>'green','waiting_reply'=>'blue','replied'=>'indigo','interview'=>'purple','offer'=>'emerald','rejected'=>'red','withdrawn'=>'orange','closed'=>'gray'];
    $priorityColors = ['urgent'=>'red','high'=>'orange','medium'=>'yellow','low'=>'gray'];
    $tc = $typeColors[$opportunity->type] ?? 'slate';
    $sc = $statusColors[$opportunity->status] ?? 'slate';
    $pc = $priorityColors[$opportunity->priority] ?? 'slate';
    $scheduledFollowUpEmails = $opportunity->emailMessages
        ->where('is_follow_up', true)
        ->whereIn('status', ['scheduled', 'queued']);
    $sentEmailCount = $opportunity->emailMessages->where('status', 'sent')->count();
    $pendingFollowUpCount = $opportunity->followUps->where('status', 'pending')->count() + $scheduledFollowUpEmails->count();
@endphp
<div class="max-w-5xl" x-data="{ tab: 'timeline' }">
    {{-- Header --}}
    <div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-{{ $tc }}-100 text-{{ $tc }}-700">{{ ucfirst($opportunity->type) }}</span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucwords(str_replace('_',' ',$opportunity->status)) }}</span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ ucfirst($opportunity->priority) }}</span>
                    @if($opportunity->deadline)
                        @php $days = now()->diffInDays($opportunity->deadline, false) @endphp
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $days < 7 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">
                            Deadline: {{ $opportunity->deadline->format('M j, Y') }}
                        </span>
                    @endif
                </div>
                <h1 class="text-xl font-bold text-slate-900 mb-1">{{ $opportunity->title }}</h1>
                @if($opportunity->organization)
                    <p class="text-slate-500 text-sm mb-1">{{ $opportunity->organization }}</p>
                @endif
                @if($opportunity->url)
                    <a href="{{ $opportunity->url }}" target="_blank" class="text-sm text-indigo-600 hover:underline">View Posting &rarr;</a>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                <a href="{{ route('compose') }}?opportunity_id={{ $opportunity->id }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Email</a>
                <a href="{{ route('opportunities.edit', $opportunity) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg">Edit</a>
                <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}" onsubmit="return confirm('Delete this opportunity?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg">Delete</button>
                </form>
            </div>
        </div>
        @if(filled($opportunity->description))
            <div class="mt-4 pt-4 border-t border-slate-100">
                <x-rich-content :value="$opportunity->description" class="text-sm text-slate-600" />
            </div>
        @endif
        @php $failedEmailCount = $opportunity->emailMessages->where('status', 'failed')->count(); @endphp
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-5 gap-4 text-center text-sm">
            <div><p class="font-semibold text-slate-800">{{ $opportunity->contacts->count() }}</p><p class="text-slate-500 text-xs">Contacts</p></div>
            <div><p class="font-semibold text-slate-800">{{ $sentEmailCount }}</p><p class="text-slate-500 text-xs">Emails Sent</p></div>
            <div><p class="font-semibold text-slate-800">{{ $pendingFollowUpCount }}</p><p class="text-slate-500 text-xs">Pending Follow-ups</p></div>
            <div><p class="font-semibold text-slate-800">{{ $opportunity->documents->count() + $opportunity->apiDocumentLinks->count() }}</p><p class="text-slate-500 text-xs">Documents</p></div>
            <div>
                <p class="font-semibold {{ $failedEmailCount > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $failedEmailCount }}</p>
                <p class="{{ $failedEmailCount > 0 ? 'text-red-500' : 'text-slate-500' }} text-xs">Failed Emails</p>
            </div>
        </div>
    </div>

    {{-- Quick Status Change --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
        <form method="POST" action="{{ route('opportunities.update-status', $opportunity) }}" class="flex items-center gap-3">
            @csrf @method('PATCH')
            <label class="text-sm font-medium text-slate-600">Quick Status:</label>
            <select name="status" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @foreach(['draft'=>'Draft','active'=>'Active','waiting_reply'=>'Waiting Reply','replied'=>'Replied','interview'=>'Interview','offer'=>'Offer','rejected'=>'Rejected','withdrawn'=>'Withdrawn','closed'=>'Closed'] as $val=>$label)
                    <option value="{{ $val }}" {{ $opportunity->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Update Status</button>
        </form>
    </div>

    {{-- Tabs --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="border-b border-slate-200 px-4 flex gap-1">
            @foreach(['timeline'=>'Timeline','contacts'=>'Contacts','emails'=>'Emails & Follow-ups','documents'=>'Documents'] as $key=>$label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-3 text-sm font-medium transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'timeline'" class="p-6">
            @if($timeline->isEmpty())
                <p class="text-center text-slate-400 py-8">No timeline events yet.</p>
            @else
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200"></div>
                    <div class="space-y-4">
                        @foreach($timeline as $event)
                        <div class="flex gap-4 pl-10 relative">
                            <div class="absolute left-2.5 w-3 h-3 rounded-full bg-indigo-400 border-2 border-white ring-1 ring-indigo-300 mt-1"></div>
                            <div class="flex-1 bg-slate-50 rounded-lg px-4 py-3">
                                <p class="text-sm text-slate-800">{{ $event->description }}</p>
                                <p class="text-xs text-slate-400 mt-1">{{ $event->happened_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div x-show="tab === 'contacts'" x-cloak class="p-6">
            @if($opportunity->contacts->isEmpty())
                <p class="text-center text-slate-400 py-8">No contacts linked. <a href="{{ route('contacts.index') }}" class="text-indigo-600 hover:underline">Browse contacts</a> to add.</p>
            @else
                <div class="space-y-2">
                    @foreach($opportunity->contacts as $contact)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200">
                        <div>
                            <a href="{{ route('contacts.show', $contact) }}" class="text-sm font-medium text-slate-800 hover:text-indigo-600">{{ $contact->full_name }}</a>
                            <p class="text-xs text-slate-500">{{ $contact->email }} &bull; {{ $contact->job_title }}</p>
                        </div>
                        <a href="{{ route('compose') }}?contact_id={{ $contact->id }}&opportunity_id={{ $opportunity->id }}" class="text-xs text-indigo-600 hover:underline">Email</a>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div x-show="tab === 'emails'" x-cloak class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Emails & Follow-ups</h3>
                <a href="{{ route('compose') }}?opportunity_id={{ $opportunity->id }}" class="text-sm text-indigo-600 hover:underline">+ Compose</a>
            </div>
            @if($failedEmailCount > 0)
            <div class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                <div class="text-sm">
                    <p class="font-semibold text-red-700">{{ $failedEmailCount }} failed email{{ $failedEmailCount > 1 ? 's' : '' }} — action required</p>
                    <p class="mt-0.5 text-red-600">
                        {{ $failedEmailCount > 1 ? 'These emails were' : 'This email was' }} not delivered. Check your SMTP credentials in
                        <a href="{{ route('email-accounts.index') }}" class="underline font-medium">Email Accounts</a>,
                        then open each failed email and resend.
                    </p>
                </div>
            </div>
            @endif
            @if($opportunity->emailMessages->isEmpty() && $opportunity->followUps->isEmpty())
                <p class="text-center text-slate-400 py-8">No emails sent for this opportunity yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($opportunity->emailMessages->sortByDesc('created_at') as $msg)
                    <a href="{{ route('emails.show', $msg) }}" class="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $msg->subject }}</p>
                            <p class="text-xs text-slate-500">To: {{ $msg->to_email }} &bull; {{ $msg->created_at->format('M j, Y') }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $msg->status === 'sent' ? 'bg-green-100 text-green-700' : ($msg->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">{{ ucfirst($msg->status) }}</span>
                    </a>
                    @endforeach
                    @foreach($opportunity->followUps->sortByDesc('due_at') as $fu)
                    <a href="{{ route('follow-ups.show', $fu) }}" class="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $fu->subject ?: 'Follow-up #' . $fu->follow_up_number }}</p>
                            <p class="text-xs text-slate-500">Due: {{ $fu->due_at->format('M j, Y g:i A') }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $fu->status === 'sent' ? 'bg-green-100 text-green-700' : ($fu->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">{{ ucfirst($fu->status) }}</span>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div x-show="tab === 'documents'" x-cloak class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Documents</h3>
                <a href="{{ route('documents.create') }}?opportunity_id={{ $opportunity->id }}" class="text-sm text-indigo-600 hover:underline">+ Upload</a>
            </div>
            @php
                $apiLinks    = $opportunity->apiDocumentLinks;
                $legacyDocs  = $opportunity->documents;
            @endphp
            @if($apiLinks->isEmpty() && $legacyDocs->isEmpty())
                <p class="text-center text-slate-400 py-8">No documents attached.</p>
            @else
                <div class="space-y-2">
                    @foreach($apiLinks as $link)
                    @php $doc = $link->document; $ver = $doc?->currentVersion; @endphp
                    @if($doc)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $doc->name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ ucfirst(str_replace('_', ' ', $doc->document_type ?? 'other')) }}
                                @if($ver) &bull; {{ $ver->original_filename }} &bull; {{ number_format($ver->size_bytes / 1024, 1) }} KB @endif
                                @if($doc->is_sensitive) &bull; <span class="text-amber-600 font-medium">Sensitive</span> @endif
                            </p>
                        </div>
                        @if($ver?->public_url)
                            <a href="{{ $ver->public_url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">View</a>
                        @elseif($ver?->storage_path)
                            <a href="{{ route('documents.api.view', $doc->id) }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">View</a>
                        @else
                            <span class="text-xs text-slate-400">Stored</span>
                        @endif
                    </div>
                    @endif
                    @endforeach
                    @foreach($legacyDocs as $doc)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $doc->name }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst($doc->document_type) }} &bull; {{ $doc->human_file_size }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('documents.view', $doc) }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">View</a>
                            <a href="{{ route('documents.download', $doc) }}" class="text-xs text-slate-500 hover:underline">Download</a>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
