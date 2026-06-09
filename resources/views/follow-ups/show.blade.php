@extends('layouts.app')
@section('title', 'Follow-up Detail')
@section('page-title', 'Follow-up Detail')
@section('content')
<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('follow-ups.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Follow-ups</a></div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">Follow-up #{{ $followUp->id }}</h1>
                <div class="flex flex-wrap gap-3 mt-2 text-sm text-slate-500">
                    @if($followUp->contact)
                        <span>Contact: <a href="{{ route('contacts.show', $followUp->contact) }}" class="text-indigo-600 hover:underline">{{ $followUp->contact->full_name }}</a></span>
                    @endif
                    @if($followUp->opportunity)
                        <span>Opportunity: <a href="{{ route('opportunities.show', $followUp->opportunity) }}" class="text-indigo-600 hover:underline">{{ Str::limit($followUp->opportunity->title, 50) }}</a></span>
                    @endif
                </div>
            </div>
            @php
                $statusColors = ['pending'=>'amber','sent'=>'green','cancelled'=>'slate','skipped'=>'slate'];
                $sc = $statusColors[$followUp->status] ?? 'slate';
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 flex-shrink-0">{{ ucfirst($followUp->status) }}</span>
        </div>

        {{-- Due date --}}
        <div class="border-t border-slate-100 pt-4 grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Due</p>
                <p class="text-slate-800">{{ $followUp->due_at?->format('M j, Y g:i A') ?? '—' }}</p>
            </div>
            @if($followUp->sent_at)
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Sent</p>
                <p class="text-slate-800">{{ $followUp->sent_at->format('M j, Y g:i A') }}</p>
            </div>
            @endif
        </div>

        {{-- Suggested subject / body --}}
        @if($followUp->subject || $followUp->body)
        <div class="border-t border-slate-100 pt-4 space-y-3">
            @if($followUp->subject)
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Suggested Subject</p>
                <p class="text-sm text-slate-800">{{ $followUp->subject }}</p>
            </div>
            @endif
            @if($followUp->body)
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Suggested Body</p>
                @php
                    $body = (string) $followUp->body;
                    $looksHtml = preg_match('/<\/?[a-z][\s\S]*>/i', $body) === 1;
                    $rendered  = $looksHtml ? $body : nl2br(e($body), false);
                @endphp
                <div class="prose prose-sm max-w-none bg-slate-50 rounded-lg p-4 text-sm text-slate-800 leading-relaxed">{!! $rendered !!}</div>
            </div>
            @endif
        </div>
        @endif

        {{-- Signature --}}
        @if($followUp->emailSignature)
        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Signature</p>
            <p class="text-sm text-slate-700">{{ $followUp->emailSignature->name }}</p>
        </div>
        @endif

        {{-- API Attachments --}}
        @if($followUp->apiAttachments->isNotEmpty())
        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Suggested Attachments</p>
            <div class="flex flex-wrap gap-2">
                @foreach($followUp->apiAttachments as $att)
                    @if($att->public_url)
                        <a href="{{ $att->public_url }}" target="_blank" rel="noopener"
                           class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs px-3 py-1.5 rounded-lg hover:bg-indigo-100">
                            {{ $att->filename }}
                        </a>
                    @else
                        <span class="bg-slate-100 text-slate-700 text-xs px-3 py-1.5 rounded-lg">{{ $att->filename }}</span>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Linked email message --}}
        @if($followUp->emailMessage)
        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Linked Email</p>
            <a href="{{ route('emails.show', $followUp->emailMessage) }}" class="text-sm text-indigo-600 hover:underline">
                {{ $followUp->emailMessage->subject ?? 'View email' }}
            </a>
        </div>
        @endif

        {{-- Actions --}}
        @if($followUp->status === 'pending')
        <div class="border-t border-slate-100 pt-4 flex gap-3">
            <form method="POST" action="{{ route('follow-ups.reschedule', $followUp->id) }}"
                  class="flex gap-2 items-center" onsubmit="return this.querySelector('input[name=due_at]').value !== ''">
                @csrf @method('PATCH')
                <input type="datetime-local" name="due_at"
                       class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Reschedule</button>
            </form>
            <form method="POST" action="{{ route('follow-ups.cancel', $followUp->id) }}"
                  onsubmit="return confirm('Cancel this follow-up?')">
                @csrf @method('PATCH')
                <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-4 py-1.5 rounded-lg">Cancel</button>
            </form>
        </div>
        @endif

    </div>
</div>
@endsection
