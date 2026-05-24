@extends('layouts.app')

@section('title', 'Add Email Account')
@section('page-title', 'Add Email Account')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('email-accounts.store') }}" x-data="{ testingSmtp: false, testingImap: false, smtpResult: null, imapResult: null }">
        @csrf

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Security Note --}}
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex gap-3">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <p class="text-sm text-amber-800"><strong>Security:</strong> Passwords are encrypted and stored securely. They will not be shown after saving.</p>
        </div>

        {{-- Basic Info --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. My Gmail" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">From Name <span class="text-red-500">*</span></label>
                    <input type="text" name="from_name" value="{{ old('from_name') }}" required placeholder="e.g. John Doe" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('from_name') border-red-400 @enderror">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sender Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@gmail.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-400 @enderror">
                </div>
            </div>
        </div>

        {{-- SMTP Settings --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-800">SMTP Settings (Outgoing)</h2>
                <button type="button"
                    @click="smtpResult = {success: false, message: 'Save the account first, then test SMTP from the account detail page.'}"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors"
                    :class="testingSmtp ? 'opacity-50 cursor-not-allowed' : ''"
                    :disabled="testingSmtp">
                    <svg x-show="!testingSmtp" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <svg x-show="testingSmtp" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="testingSmtp ? 'Testing...' : 'Test SMTP'"></span>
                </button>
            </div>
            <div x-show="smtpResult !== null" class="mb-4 px-3 py-2 rounded-lg text-sm" :class="smtpResult?.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'" x-text="smtpResult?.message"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Host <span class="text-red-500">*</span></label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host') }}" placeholder="smtp.gmail.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Port <span class="text-red-500">*</span></label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', 587) }}" placeholder="587" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Encryption</label>
                    <select name="smtp_encryption" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="tls" {{ old('smtp_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ old('smtp_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        <option value="none" {{ old('smtp_encryption') === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input type="text" name="smtp_username" value="{{ old('smtp_username') }}" placeholder="usually your email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input type="password" name="smtp_password" placeholder="••••••••" autocomplete="new-password" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        {{-- IMAP Settings --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-800">IMAP Settings (Incoming)</h2>
                <button type="button"
                    @click="imapResult = {success: false, message: 'Save the account before testing IMAP.'}"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors"
                    :disabled="testingImap">
                    <svg x-show="!testingImap" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <svg x-show="testingImap" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="testingImap ? 'Testing...' : 'Test IMAP'"></span>
                </button>
            </div>
            <div x-show="imapResult !== null" class="mb-4 px-3 py-2 rounded-lg text-sm" :class="imapResult?.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'" x-text="imapResult?.message"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">IMAP Host</label>
                    <input type="text" name="imap_host" value="{{ old('imap_host') }}" placeholder="imap.gmail.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Port</label>
                    <input type="number" name="imap_port" value="{{ old('imap_port', 993) }}" placeholder="993" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Encryption</label>
                    <select name="imap_encryption" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="ssl" {{ old('imap_encryption', 'ssl') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        <option value="tls" {{ old('imap_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="none" {{ old('imap_encryption') === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input type="text" name="imap_username" value="{{ old('imap_username') }}" placeholder="usually your email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input type="password" name="imap_password" placeholder="••••••••" autocomplete="new-password" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        {{-- Limits --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Sending Limits</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Daily Limit</label>
                    <input type="number" name="daily_limit" value="{{ old('daily_limit', 100) }}" min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-slate-400 mt-1">Max emails per day</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Hourly Limit</label>
                    <input type="number" name="hourly_limit" value="{{ old('hourly_limit', 20) }}" min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-slate-400 mt-1">Max emails per hour</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Min Delay (seconds)</label>
                    <input type="number" name="min_delay_seconds" value="{{ old('min_delay_seconds', 30) }}" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-slate-400 mt-1">Delay between sends</p>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Notes</h2>
            <textarea name="notes" rows="3" placeholder="Optional notes about this account..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">Save Account</button>
            <a href="{{ route('email-accounts.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
