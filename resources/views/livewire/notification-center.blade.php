<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-slate-800">Notifications</h2>
            @if($unreadCount > 0)
                <span class="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $unreadCount }} unread</span>
            @endif
        </div>
        @if($unreadCount > 0)
            <button
                wire:click="markAllRead"
                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
            >
                Mark all read
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-center">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Type</label>
            <select wire:model.live="filterType" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="reply_received">Reply Received</option>
                <option value="followup_due">Follow-up Due</option>
                <option value="email_failed">Email Failed</option>
                <option value="email_sent">Email Sent</option>
                <option value="positive_reply">Positive Reply</option>
                <option value="daily_summary">Daily Summary</option>
                <option value="account_sync_failed">Sync Failed</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select wire:model.live="filterRead" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All</option>
                <option value="unread">Unread</option>
                <option value="read">Read</option>
            </select>
        </div>
    </div>

    {{-- Notification List --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @php
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
        @endphp

        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data;
                    $icon = $data['icon'] ?? 'bell';
                    $ic   = $iconMap[$icon] ?? $iconMap['bell'];
                    $isUnread = is_null($notification->read_at);
                @endphp
                <div class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 transition-colors {{ $isUnread ? 'bg-blue-50/30' : '' }}">
                    <div class="w-10 h-10 rounded-full {{ $ic['bg'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 {{ $ic['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $ic['svg'] !!}
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">{{ $data['title'] ?? 'Notification' }}</p>
                            <span class="text-xs text-slate-400 flex-shrink-0">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-slate-600 mt-0.5">{{ $data['body'] ?? '' }}</p>
                        <div class="flex items-center gap-3 mt-2">
                            @if(! empty($data['action_url']))
                                <a href="{{ $data['action_url'] }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ $data['action_label'] ?? 'View' }} →
                                </a>
                            @endif
                            @if($isUnread)
                                <button wire:click="markRead('{{ $notification->id }}')" class="text-xs text-slate-500 hover:text-slate-700">
                                    Mark as read
                                </button>
                            @endif
                        </div>
                    </div>
                    @if($isUnread)
                        <div class="w-2.5 h-2.5 bg-blue-500 rounded-full flex-shrink-0 mt-1.5"></div>
                    @endif
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <svg class="w-10 h-10 text-slate-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-sm text-slate-400">No notifications found.</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

    {{-- Notification Preferences --}}
    <div class="bg-white border border-slate-200 rounded-xl mt-6">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-800">Notification Preferences</h3>
            <p class="text-xs text-slate-500 mt-0.5">Choose which notifications you receive and on which channels.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Notification</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">In-App</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Push <span class="text-slate-300">(soon)</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $typeLabels = [
                            'reply_received'     => 'Reply Received',
                            'followup_due'       => 'Follow-up Due',
                            'email_failed'       => 'Email Failed',
                            'email_sent'         => 'Email Sent',
                            'positive_reply'     => 'Positive Reply',
                            'daily_summary'      => 'Daily Summary',
                            'account_sync_failed'=> 'Account Sync Failed',
                        ];
                    @endphp
                    @foreach($typeLabels as $type => $label)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-medium text-slate-700">{{ $label }}</td>
                            @foreach(['database', 'mail', 'push'] as $channel)
                                <td class="text-center px-4 py-3">
                                    <form
                                        method="POST"
                                        action="{{ route('notifications.preferences') }}"
                                        class="inline-block"
                                    >
                                        @csrf
                                        <input type="hidden" name="notification_type" value="{{ $type }}">
                                        <input type="hidden" name="channel" value="{{ $channel }}">
                                        <input type="hidden" name="enabled" value="{{ $preferences[$type][$channel] ? '0' : '1' }}">
                                        <button
                                            type="submit"
                                            class="w-8 h-5 rounded-full transition-colors duration-200 flex items-center {{ $preferences[$type][$channel] ? 'bg-indigo-500' : 'bg-slate-200' }} {{ $channel === 'push' ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            {{ $channel === 'push' ? 'disabled' : '' }}
                                            title="{{ $channel === 'push' ? 'Push notifications coming soon' : '' }}"
                                        >
                                            <span class="w-3.5 h-3.5 bg-white rounded-full shadow transform transition-transform duration-200 {{ $preferences[$type][$channel] ? 'translate-x-3.5' : 'translate-x-0.5' }}"></span>
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
