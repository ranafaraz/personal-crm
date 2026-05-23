<div class="relative" x-data="{ open: @entangle('open') }">
    {{-- Bell Button --}}
    <button
        wire:click="toggle"
        class="relative p-1.5 rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        aria-label="Notifications"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-bold px-0.5 leading-none">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-cloak
        @click.outside="$wire.open = false"
        class="absolute right-0 top-full mt-2 w-80 bg-white border border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
            <span class="text-sm font-semibold text-slate-800">
                Notifications
                @if($unreadCount > 0)
                    <span class="ml-1 bg-red-100 text-red-600 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $unreadCount }}</span>
                @endif
            </span>
            <div class="flex items-center gap-2">
                @if($unreadCount > 0)
                    <button wire:click="markAllRead" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        Mark all read
                    </button>
                @endif
                <a href="{{ route('notifications.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
                    View all
                </a>
            </div>
        </div>

        {{-- Notification List --}}
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data;
                    $icon = $data['icon'] ?? 'bell';
                    $iconMap = [
                        'reply'       => ['bg' => 'bg-blue-100',   'text' => 'text-blue-600',   'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>'],
                        'clock'       => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                        'x-circle'    => ['bg' => 'bg-red-100',    'text' => 'text-red-600',    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                        'check-circle'=> ['bg' => 'bg-green-100',  'text' => 'text-green-600',  'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                        'star'        => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>'],
                        'chart-bar'   => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
                        'exclamation' => ['bg' => 'bg-amber-100',  'text' => 'text-amber-600',  'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>'],
                        'bell'        => ['bg' => 'bg-slate-100',  'text' => 'text-slate-600',  'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>'],
                    ];
                    $ic = $iconMap[$icon] ?? $iconMap['bell'];
                @endphp
                <div class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 group {{ is_null($notification->read_at) ? 'bg-blue-50/40' : '' }}">
                    <div class="w-8 h-8 rounded-full {{ $ic['bg'] }} flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 {{ $ic['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $ic['svg'] !!}
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-800 truncate">{{ $data['title'] ?? 'Notification' }}</p>
                        <p class="text-xs text-slate-500 line-clamp-2 mt-0.5">{{ $data['body'] ?? '' }}</p>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                            @if(! $notification->read_at)
                                <button
                                    wire:click="markRead('{{ $notification->id }}')"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    Mark read
                                </button>
                            @endif
                        </div>
                    </div>
                    @if(! $notification->read_at)
                        <div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></div>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-xs text-slate-400">You're all caught up!</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="px-4 py-2.5 border-t border-slate-200 bg-slate-50">
            <a href="{{ route('notifications.index') }}" class="block text-center text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                View all notifications →
            </a>
        </div>
    </div>

    {{-- Polling: refresh every 30s --}}
    <div wire:poll.30000ms="refresh"></div>
</div>
