@extends('layouts.app')
@section('title', 'Edit LinkedIn App')

@section('content')
<div class="p-6 max-w-xl space-y-5">

    <div>
        <a href="{{ route('social-studio.connections') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Connections</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Edit — {{ $app->label }}</h1>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-600">
        <span class="font-medium">Redirect URI registered in LinkedIn:</span>
        <span class="font-mono ml-1 break-all">{{ $redirectUri }}</span>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.oauth-apps.update', $app->id) }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Label <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label', $app->label) }}" required maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Client ID <span class="text-red-500">*</span></label>
                <input type="text" name="client_id" value="{{ old('client_id', $app->client_id) }}" required maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">New Client Secret <span class="text-slate-400">(leave blank to keep existing)</span></label>
                <input type="password" name="client_secret" maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="Enter new secret to rotate it">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Scopes</label>
                <input type="text" name="scopes" value="{{ old('scopes', $app->scopes) }}" maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save Changes
            </button>
            <a href="{{ route('social-studio.connections') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
