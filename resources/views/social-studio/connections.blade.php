@extends('layouts.app')
@section('title', 'Social Connections')

@section('content')
<div class="p-6 space-y-6 max-w-3xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Social Connections</h1>
            <p class="text-sm text-slate-500 mt-1">Connect your LinkedIn accounts through your registered developer apps.</p>
        </div>
        <a href="{{ route('social-studio.oauth-apps.index') }}"
           class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Manage Apps
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- LinkedIn section --}}
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-blue-700 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
            </svg>
            <h2 class="text-base font-semibold text-slate-800">LinkedIn</h2>
        </div>

        @forelse($oauthApps->where('provider_key', 'linkedin') as $app)
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            {{-- App header --}}
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $app->label }}</p>
                        @if($app->is_default)
                            <span class="text-[11px] font-semibold bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5">Default App</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5 font-mono">{{ $app->client_id }}</p>
                </div>
                <a href="{{ route('social-studio.connections.connect', ['app_id' => $app->id]) }}"
                   class="flex-shrink-0 inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Account
                </a>
            </div>

            {{-- Connected accounts --}}
            @if($app->accounts->count())
            <div class="border-t border-slate-100 pt-3 space-y-2">
                @foreach($app->accounts as $account)
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $account->status === 'connected' ? 'bg-green-500' : ($account->status === 'reauthorization_required' ? 'bg-amber-400' : 'bg-slate-300') }}"></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm text-slate-800 font-medium truncate">{{ $account->display_name }}</span>
                                @if($account->is_default)
                                    <span class="text-[10px] font-semibold bg-green-100 text-green-700 rounded-full px-1.5 py-0.5 flex-shrink-0">Default</span>
                                @endif
                            </div>
                            <p class="text-xs @if($account->status === 'connected') text-green-600 @elseif($account->status === 'reauthorization_required') text-amber-600 @else text-slate-400 @endif">
                                @if($account->status === 'connected') Connected
                                @elseif($account->status === 'reauthorization_required') Reauth required
                                @else Disconnected
                                @endif
                                @if($account->last_verified_at)
                                    · verified {{ $account->last_verified_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if(! $account->is_default)
                        <form method="POST" action="{{ route('social-studio.connections.set-default', $account->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-slate-500 hover:text-indigo-600 underline">Set default</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('social-studio.connections.verify', $account->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-slate-500 hover:text-slate-700 underline">Verify</button>
                        </form>
                        <form method="POST" action="{{ route('social-studio.connections.disconnect', $account->id) }}"
                              onsubmit="return confirm('Disconnect {{ addslashes($account->display_name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 underline">Disconnect</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="border-t border-slate-100 pt-3">
                <p class="text-xs text-slate-400">No accounts connected yet. Click <strong>Add Account</strong> to start OAuth.</p>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-xl border border-dashed border-slate-300 p-8 text-center">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
            </svg>
            <p class="text-sm font-medium text-slate-500">No LinkedIn apps configured yet.</p>
            <p class="text-xs text-slate-400 mt-1">Add your LinkedIn Developer App credentials first.</p>
            <a href="{{ route('social-studio.oauth-apps.create') }}"
               class="mt-3 inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Add LinkedIn App
            </a>
        </div>
        @endforelse
    </div>

    {{-- Coming-soon platforms --}}
    @foreach($providers->where('status', 'coming_soon') as $provider)
    <div class="bg-white rounded-xl border border-slate-200 p-5 opacity-60">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ $provider->name }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Coming soon</p>
                </div>
            </div>
            <span class="text-xs bg-slate-100 text-slate-500 rounded-full px-3 py-1 font-medium">Coming Soon</span>
        </div>
    </div>
    @endforeach

</div>
@endsection
