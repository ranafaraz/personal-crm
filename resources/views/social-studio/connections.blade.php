@extends('layouts.app')
@section('title', 'Social Connections')

@section('content')
<div class="p-6 space-y-6 max-w-3xl">

    <div>
        <h1 class="text-2xl font-bold text-slate-800">Social Connections</h1>
        <p class="text-sm text-slate-500 mt-1">Connect your social accounts to enable publishing.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    @foreach($providers as $provider)
    @php $account = $accounts[$provider->key] ?? null; @endphp
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                    @if($provider->key === 'linkedin')
                    <svg class="w-6 h-6 text-blue-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                    </svg>
                    @else
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ $provider->name }}</p>
                    @if($account)
                        <p class="text-xs mt-0.5 @if($account->status === 'connected') text-green-600 @elseif($account->status === 'reauthorization_required') text-red-600 @else text-slate-400 @endif font-medium">
                            @if($account->status === 'connected')
                                Connected as {{ $account->display_name }}
                            @elseif($account->status === 'reauthorization_required')
                                Token expired — reconnect required
                            @else
                                Disconnected
                            @endif
                        </p>
                    @elseif($provider->status === 'coming_soon')
                        <p class="text-xs text-slate-400 mt-0.5">Coming soon</p>
                    @else
                        <p class="text-xs text-slate-400 mt-0.5">Not connected</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($provider->status === 'coming_soon')
                    <span class="text-xs bg-slate-100 text-slate-500 rounded-full px-3 py-1 font-medium">Coming Soon</span>
                @elseif($account && $account->status === 'connected')
                    <form method="POST" action="{{ route('social-studio.connections.verify', $account->id) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-slate-500 hover:text-slate-700 underline">Verify</button>
                    </form>
                    <form method="POST" action="{{ route('social-studio.connections.disconnect', $account->id) }}"
                          onsubmit="return confirm('Disconnect LinkedIn?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 underline">Disconnect</button>
                    </form>
                @elseif($provider->key === 'linkedin')
                    <a href="{{ route('social-studio.connections.connect') }}"
                       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        Connect
                    </a>
                @endif
            </div>
        </div>

        @if($account && $account->status === 'connected')
        <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500 flex gap-4">
            @if($account->last_verified_at)
                <span>Last verified: {{ $account->last_verified_at->diffForHumans() }}</span>
            @endif
            @if($account->token_expires_at)
                <span>Token expires: {{ $account->token_expires_at->format('d M Y') }}</span>
            @endif
        </div>
        @endif
    </div>
    @endforeach

</div>
@endsection
