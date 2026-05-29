@extends('layouts.app')
@section('title', 'Add LinkedIn App')

@section('content')
<div class="p-6 max-w-xl space-y-5">

    <div>
        <a href="{{ route('social-studio.connections') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Connections</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Add LinkedIn Developer App</h1>
    </div>

    {{-- Setup instructions --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-2 text-sm text-blue-800">
        <p class="font-semibold">Before adding your app:</p>
        <ol class="list-decimal list-inside space-y-1 text-xs">
            <li>Go to <a href="https://www.linkedin.com/developers/apps" target="_blank" class="underline font-medium">linkedin.com/developers/apps</a> and open your app.</li>
            <li>Under the <strong>Auth</strong> tab, add this exact URL as an <strong>Authorized Redirect URL</strong>:</li>
        </ol>
        <div class="bg-white border border-blue-200 rounded-lg px-3 py-2 font-mono text-xs select-all break-all">
            {{ $redirectUri }}
        </div>
        <p class="text-xs">Then copy your <strong>Client ID</strong> and <strong>Primary Client Secret</strong> from the same Auth tab.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.oauth-apps.store') }}" class="space-y-4">
        @csrf

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Label <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label') }}" required maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="e.g. personal-crm app, Client A LinkedIn">
                <p class="text-xs text-slate-400 mt-1">A name to identify this app in the CRM.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Client ID <span class="text-red-500">*</span></label>
                <input type="text" name="client_id" value="{{ old('client_id') }}" required maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="86xxxxxxxxxx">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Primary Client Secret <span class="text-red-500">*</span></label>
                <input type="password" name="client_secret" required maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="WPL_AP1.xxxxx">
                <p class="text-xs text-slate-400 mt-1">Stored encrypted. Never shown again after saving.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Scopes</label>
                <input type="text" name="scopes" value="{{ old('scopes', 'w_member_social openid profile email') }}" maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                <p class="text-xs text-slate-400 mt-1">Space-separated. Default covers posting + identity.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_default" class="text-sm text-slate-700">Set as default app</label>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <p class="text-xs text-slate-500">
                    <strong>Redirect URI (read-only)</strong> — this is what LinkedIn will send the authorization code to:
                </p>
                <p class="text-xs font-mono text-slate-600 mt-1 break-all">{{ $redirectUri }}</p>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save App
            </button>
            <a href="{{ route('social-studio.connections') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
