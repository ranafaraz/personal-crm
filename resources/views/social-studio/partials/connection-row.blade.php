<div class="flex items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $account->status === 'connected' ? 'bg-green-500' : ($account->status === 'reauthorization_required' ? 'bg-amber-400' : 'bg-slate-300') }}"></span>
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <p class="text-sm text-slate-800 font-medium truncate">{{ $account->display_name ?: $account->provider?->name }}</p>
                @if($account->is_default)
                    <span class="text-[10px] font-semibold bg-green-100 text-green-700 rounded-full px-1.5 py-0.5 flex-shrink-0">Default</span>
                @endif
            </div>
            <p class="text-xs text-slate-400 mt-0.5 truncate">
                {{ $account->provider?->name }}
                @if($account->public_profile_url)
                    · {{ $account->public_profile_url }}
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
                <button type="submit" class="text-xs text-slate-500 hover:text-indigo-600 underline">Default</button>
            </form>
        @endif
        <form method="POST" action="{{ route('social-studio.connections.verify', $account->id) }}">
            @csrf @method('PATCH')
            <button type="submit" class="text-xs text-slate-500 hover:text-slate-700 underline">Verify</button>
        </form>
        <form method="POST" action="{{ route('social-studio.connections.disconnect', $account->id) }}"
              onsubmit="return confirm('Disconnect {{ addslashes($account->display_name ?: $account->provider?->name) }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-red-500 hover:text-red-700 underline">Disconnect</button>
        </form>
    </div>
</div>
