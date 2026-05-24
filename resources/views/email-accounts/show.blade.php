@extends('layouts.app')

@section('title', $account->name)
@section('page-title', $account->name)

@section('content')
<div class="max-w-5xl space-y-6" x-data="{ testingSmtp: false, testingImap: false, smtpResult: null, imapResult: null }">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $account->name }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ $account->email }}</p>
                <p class="text-xs text-slate-400 mt-2">From: {{ $account->from_name }}</p>
            </div>
            <div class="flex gap-2 flex-wrap justify-end">
                <button type="button"
                    @click="testingSmtp = true; smtpResult = null; fetch('{{ route('email-accounts.test-smtp', $account) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } }).then(r => r.json()).then(d => { smtpResult = d; testingSmtp = false; }).catch(e => { smtpResult = {success: false, message: 'Request failed: ' + e.message}; testingSmtp = false; })"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg"
                    :disabled="testingSmtp">
                    <span x-text="testingSmtp ? 'Testing…' : 'Test SMTP'"></span>
                </button>
                <button type="button"
                    @click="testingImap = true; imapResult = null; fetch('{{ route('email-accounts.test-imap', $account) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } }).then(r => r.json()).then(d => { imapResult = d; testingImap = false; }).catch(e => { imapResult = {success: false, message: 'Request failed: ' + e.message}; testingImap = false; })"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg"
                    :disabled="testingImap">
                    <span x-text="testingImap ? 'Testing…' : 'Test IMAP'"></span>
                </button>
                <a href="{{ route('email-accounts.edit', $account) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg">Edit</a>
                <form method="POST" action="{{ route('email-accounts.sync-inbox', $account) }}">
                    @csrf
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Sync Inbox</button>
                </form>
            </div>
        </div>

        {{-- Inline test results --}}
        <div x-show="smtpResult !== null" x-cloak class="mt-4 px-3 py-2 rounded-lg text-sm" :class="smtpResult?.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
            <strong>SMTP:</strong> <span x-text="smtpResult?.message"></span>
        </div>
        <div x-show="imapResult !== null" x-cloak class="mt-2 px-3 py-2 rounded-lg text-sm" :class="imapResult?.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
            <strong>IMAP:</strong> <span x-text="imapResult?.message"></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6 text-sm">
            <div>
                <p class="text-xs text-slate-500">SMTP</p>
                <p class="font-medium text-slate-800">{{ $account->smtp_host }}:{{ $account->smtp_port }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">IMAP</p>
                <p class="font-medium text-slate-800">{{ $account->imap_host ?: 'Not configured' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Daily Usage</p>
                <p class="font-medium text-slate-800">{{ $account->emails_sent_today }}/{{ $account->daily_limit }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Last Sync</p>
                <p class="font-medium text-slate-800">{{ $account->last_sync_at ? $account->last_sync_at->diffForHumans() : 'Never' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-800">Recent Sent Emails</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($sentEmails as $email)
                <a href="{{ route('emails.show', $email) }}" class="block px-5 py-3 hover:bg-slate-50">
                    <p class="text-sm font-medium text-slate-800">{{ $email->subject }}</p>
                    <p class="text-xs text-slate-500">To {{ $email->to_email }} &bull; {{ $email->created_at->format('M j, Y') }}</p>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No sent emails for this account yet.</p>
            @endforelse
        </div>
        @if($sentEmails->hasPages())
            <div class="px-5 py-4 border-t border-slate-200">{{ $sentEmails->links() }}</div>
        @endif
    </div>
</div>
@endsection
