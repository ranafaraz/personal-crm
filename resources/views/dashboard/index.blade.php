@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
{{-- Filters --}}
<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Outreach Type</label>
            <select name="type" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="job" {{ request('type') === 'job' ? 'selected' : '' }}>Job</option>
                <option value="scholarship" {{ request('type') === 'scholarship' ? 'selected' : '' }}>Scholarship</option>
                <option value="research" {{ request('type') === 'research' ? 'selected' : '' }}>Research</option>
                <option value="grant" {{ request('type') === 'grant' ? 'selected' : '' }}>Grant</option>
                <option value="networking" {{ request('type') === 'networking' ? 'selected' : '' }}>Networking</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email Account</label>
            <select name="email_account_id" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Accounts</option>
                @foreach($emailAccounts ?? [] as $account)
                    <option value="{{ $account->id }}" {{ request('email_account_id') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select name="status" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="waiting_reply" {{ request('status') === 'waiting_reply' ? 'selected' : '' }}>Waiting Reply</option>
                <option value="replied" {{ request('status') === 'replied' ? 'selected' : '' }}>Replied</option>
                <option value="interview" {{ request('status') === 'interview' ? 'selected' : '' }}>Interview</option>
                <option value="offer" {{ request('status') === 'offer' ? 'selected' : '' }}>Offer</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Priority</label>
            <select name="priority" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Priorities</option>
                <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">Filter</button>
            <a href="{{ route('dashboard') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">Reset</a>
        </div>
    </form>
</div>

{{-- Top Row - 6 Stat Cards --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Opportunities</p>
        <p class="text-3xl font-bold text-slate-800">{{ $stats['total_opportunities'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">All time</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Active</p>
        <p class="text-3xl font-bold text-indigo-600">{{ $stats['active_opportunities'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Active / waiting</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Waiting Reply</p>
        <p class="text-3xl font-bold text-blue-600">{{ $stats['waiting_reply'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Awaiting response</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Follow-ups Today</p>
        <p class="text-3xl font-bold text-orange-500">{{ $stats['follow_ups_due_today'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Due today</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Scheduled Today</p>
        <p class="text-3xl font-bold text-purple-600">{{ $stats['scheduled_today'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Emails queued</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Needs Review</p>
        <p class="text-3xl font-bold text-yellow-500">{{ $stats['replies_needing_review'] ?? 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Replies pending</p>
    </div>
</div>

{{-- Second Row - 3 Stat Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wide">Failed Sends</p>
            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-3xl font-bold text-red-700">{{ $stats['failed_sends_24h'] ?? 0 }}</p>
        <p class="text-xs text-red-500 mt-1">Last 24 hours</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Positive Replies</p>
            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-3xl font-bold text-green-700">{{ $stats['positive_replies'] ?? 0 }}</p>
        <p class="text-xs text-green-500 mt-1">Positive sentiment</p>
    </div>
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
            <p class="text-xs font-medium text-orange-600 uppercase tracking-wide">Stale Opportunities</p>
            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-3xl font-bold text-orange-700">{{ $stats['stale_opportunities'] ?? 0 }}</p>
        <p class="text-xs text-orange-500 mt-1">No activity 14+ days</p>
    </div>
</div>

{{-- Email Account Health --}}
<div class="bg-white border border-slate-200 rounded-xl mb-6">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Email Account Health</h2>
        <a href="{{ route('email-accounts.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Manage Accounts</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Account</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">SMTP</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">IMAP</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Daily Usage</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Sync</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($emailAccounts ?? [] as $account)
                    @php
                        $usagePct = $account->daily_limit > 0 ? min(100, round(($account->emails_sent_today / $account->daily_limit) * 100)) : 0;
                        $barColor = $usagePct >= 90 ? 'bg-red-500' : ($usagePct >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $account->name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $account->email }}</td>
                        <td class="px-5 py-3">
                            @if($account->smtp_status === 'ok')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> OK
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Error
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($account->imap_status === 'ok')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> OK
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Error
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 w-40">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-slate-200 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ $usagePct }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500 whitespace-nowrap">{{ $account->emails_sent_today ?? 0 }}/{{ $account->daily_limit ?? 0 }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-xs">{{ $account->last_sync_at ? $account->last_sync_at->diffForHumans() : 'Never' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex gap-2">
                                <button class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Sync</button>
                                <button class="text-xs text-slate-600 hover:text-slate-800 font-medium">Test</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-slate-400 text-sm">
                            No email accounts configured.
                            <a href="{{ route('email-accounts.create') }}" class="text-indigo-600 hover:underline ml-1">Add one now</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Two Column Row: Recent Replies + Upcoming Deadlines --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Recent Replies --}}
    <div class="bg-white border border-slate-200 rounded-xl">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800">Recent Replies</h2>
            <a href="{{ route('inbox.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View Inbox</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($recentReplies ?? [] as $reply)
                @php
                    $sentimentColors = [
                        'positive' => 'text-green-700 bg-green-100',
                        'negative' => 'text-red-700 bg-red-100',
                        'neutral'  => 'text-slate-600 bg-slate-100',
                        'unknown'  => 'text-slate-500 bg-slate-100',
                    ];
                    $sc = $sentimentColors[$reply->sentiment ?? 'unknown'] ?? $sentimentColors['unknown'];
                @endphp
                <a href="{{ route('inbox.index') }}" class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $reply->from_email }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ $reply->subject }}</p>
                        @if($reply->opportunity)
                            <p class="text-xs text-indigo-600 truncate">{{ $reply->opportunity->title }}</p>
                        @endif
                    </div>
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        <span class="text-xs text-slate-400">{{ $reply->received_at ? $reply->received_at->diffForHumans() : '' }}</span>
                        <span class="text-xs font-medium px-1.5 py-0.5 rounded-full {{ $sc }}">{{ ucfirst($reply->sentiment ?? 'unknown') }}</span>
                    </div>
                </a>
            @empty
                <div class="px-5 py-8 text-center text-slate-400 text-sm">No recent replies.</div>
            @endforelse
        </div>
    </div>

    {{-- Upcoming Deadlines --}}
    <div class="bg-white border border-slate-200 rounded-xl">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800">Upcoming Deadlines</h2>
            <a href="{{ route('opportunities.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View All</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($upcomingDeadlines ?? [] as $opp)
                @php
                    $daysLeft = now()->diffInDays($opp->deadline, false);
                    $rowBg = $daysLeft < 7 ? 'bg-red-50' : ($daysLeft < 14 ? 'bg-yellow-50' : '');
                    $daysColor = $daysLeft < 7 ? 'text-red-600 font-bold' : ($daysLeft < 14 ? 'text-yellow-600 font-semibold' : 'text-slate-500');
                    $typeColors = ['job'=>'bg-blue-100 text-blue-700','scholarship'=>'bg-purple-100 text-purple-700','research'=>'bg-indigo-100 text-indigo-700','grant'=>'bg-yellow-100 text-yellow-700','networking'=>'bg-green-100 text-green-700'];
                    $tc = $typeColors[$opp->type] ?? 'bg-slate-100 text-slate-700';
                    $priorityColors = ['urgent'=>'bg-red-100 text-red-700','high'=>'bg-orange-100 text-orange-700','medium'=>'bg-yellow-100 text-yellow-700','low'=>'bg-slate-100 text-slate-600'];
                    $pc = $priorityColors[$opp->priority] ?? 'bg-slate-100 text-slate-600';
                @endphp
                <a href="{{ route('opportunities.show', $opp) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors {{ $rowBg }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $opp->title }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs px-1.5 py-0.5 rounded-full font-medium {{ $tc }}">{{ ucfirst($opp->type) }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-full font-medium {{ $pc }}">{{ ucfirst($opp->priority) }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end flex-shrink-0">
                        <span class="text-xs text-slate-500">{{ $opp->deadline->format('M d') }}</span>
                        <span class="text-xs {{ $daysColor }}">{{ $daysLeft }}d left</span>
                    </div>
                </a>
            @empty
                <div class="px-5 py-8 text-center text-slate-400 text-sm">No upcoming deadlines in 30 days.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Outreach Funnel --}}
<div class="bg-white border border-slate-200 rounded-xl mb-6 p-5">
    <h2 class="text-sm font-semibold text-slate-800 mb-4">Outreach Funnel</h2>
    @php
        $funnel = $funnelData ?? [];
        $funnelStages = [
            'draft'         => ['label' => 'Draft',         'color' => 'bg-slate-400'],
            'active'        => ['label' => 'Active',        'color' => 'bg-blue-500'],
            'waiting_reply' => ['label' => 'Waiting Reply', 'color' => 'bg-indigo-500'],
            'replied'       => ['label' => 'Replied',       'color' => 'bg-purple-500'],
            'interview'     => ['label' => 'Interview',     'color' => 'bg-yellow-500'],
            'offer'         => ['label' => 'Offer',         'color' => 'bg-green-500'],
            'rejected'      => ['label' => 'Rejected',      'color' => 'bg-red-400'],
        ];
        $maxCount = max(1, max(array_values($funnel + array_fill_keys(array_keys($funnelStages), 0))));
    @endphp
    <div class="space-y-3">
        @foreach($funnelStages as $key => $stage)
            @php $count = $funnel[$key] ?? 0; $pct = round(($count / $maxCount) * 100); @endphp
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-600 w-28 text-right font-medium">{{ $stage['label'] }}</span>
                <div class="flex-1 bg-slate-100 rounded-full h-5 overflow-hidden">
                    <div class="{{ $stage['color'] }} h-5 rounded-full flex items-center px-2 transition-all" style="width: max({{ $pct }}%, {{ $count > 0 ? '2rem' : '0' }})">
                        @if($count > 0)
                            <span class="text-xs font-semibold text-white">{{ $count }}</span>
                        @endif
                    </div>
                </div>
                <span class="text-xs text-slate-400 w-8">{{ $count }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- Two Column Row: Response Rate + Quick Actions --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Response Rate by Type --}}
    <div class="bg-white border border-slate-200 rounded-xl">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-800">Response Rate by Type</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-slate-500 uppercase">Type</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 uppercase">Sent</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 uppercase">Replies</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-slate-500 uppercase">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach(['job', 'scholarship', 'research', 'grant', 'networking'] as $type)
                        @php
                            $row = $responseRates[$type] ?? ['sent' => 0, 'replies' => 0];
                            $rate = $row['sent'] > 0 ? round(($row['replies'] / $row['sent']) * 100, 1) : 0;
                            $rateColor = $rate >= 30 ? 'text-green-600' : ($rate >= 15 ? 'text-yellow-600' : 'text-red-500');
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-medium text-slate-700 capitalize">{{ $type }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-600">{{ $row['sent'] }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-600">{{ $row['replies'] }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold {{ $rateColor }}">{{ $rate }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('opportunities.create') }}" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Opportunity
            </a>
            <a href="{{ route('contacts.create') }}" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Add Contact
            </a>
            <a href="{{ route('compose') }}" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Compose Email
            </a>
            <a href="{{ route('imports.create') }}" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import CSV
            </a>
            <a href="{{ route('documents.index') }}" class="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Upload File
            </a>
            <button class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-3 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Sync All Inboxes
            </button>
        </div>
    </div>
</div>
@endsection
