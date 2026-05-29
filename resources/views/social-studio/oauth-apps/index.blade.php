@extends('layouts.app')
@section('title', 'LinkedIn Apps')

@section('content')
<div class="p-6 max-w-3xl space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">LinkedIn Developer Apps</h1>
            <p class="text-sm text-slate-500 mt-1">Each app uses its own Client ID &amp; Secret from the LinkedIn Developer portal.</p>
        </div>
        <a href="{{ route('social-studio.oauth-apps.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add App
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    <div class="space-y-3">
        @forelse($apps as $app)
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $app->label }}</p>
                        @if($app->is_default)
                            <span class="text-[11px] font-semibold bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5">Default</span>
                        @endif
                        @if(! $app->is_active)
                            <span class="text-[11px] font-semibold bg-slate-100 text-slate-500 rounded-full px-2 py-0.5">Inactive</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Client ID: <span class="font-mono">{{ $app->client_id }}</span></p>
                    <p class="text-xs text-slate-500">Scopes: {{ $app->scopes }}</p>

                    {{-- Linked accounts --}}
                    @if($app->accounts->count())
                    <div class="mt-2 space-y-1">
                        @foreach($app->accounts as $account)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $account->status === 'connected' ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                            <span class="text-slate-700">{{ $account->display_name }}</span>
                            <span class="text-slate-400">{{ ucfirst($account->status) }}</span>
                            @if($account->is_default)
                                <span class="text-indigo-500 font-medium">· Default account</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-slate-400 mt-1">No account connected yet.</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    @if(! $app->is_default)
                    <form method="POST" action="{{ route('social-studio.oauth-apps.set-default', $app->id) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-slate-500 hover:text-indigo-600 underline">Set default</button>
                    </form>
                    @endif
                    <a href="{{ route('social-studio.connections.connect', ['app_id' => $app->id]) }}"
                       class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg transition">
                        Connect
                    </a>
                    <a href="{{ route('social-studio.oauth-apps.edit', $app->id) }}"
                       class="text-xs text-slate-500 hover:text-slate-700 underline">Edit</a>
                    <form method="POST" action="{{ route('social-studio.oauth-apps.destroy', $app->id) }}"
                          onsubmit="return confirm('Remove this app and disconnect any linked accounts?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 underline">Remove</button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-14 text-slate-400 bg-white rounded-xl border border-slate-200">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <p class="text-sm font-medium">No LinkedIn apps configured yet.</p>
            <a href="{{ route('social-studio.oauth-apps.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Add your first app</a>
        </div>
        @endforelse
    </div>

</div>
@endsection
