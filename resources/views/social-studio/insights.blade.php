@extends('layouts.app')
@section('title', 'Social Insights')

@section('content')
<div class="p-6 space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Insights</h1>
            <p class="text-sm text-slate-500 mt-1">Publishing health and available platform analytics for connected channels.</p>
        </div>
        <form method="POST" action="{{ route('social-studio.insights.sync') }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sync LinkedIn
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    @if(! $hasData)
        <div class="bg-white rounded-lg border border-slate-200 p-10 text-center">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-slate-500 text-sm">No connected channels yet.</p>
            <a href="{{ route('social-studio.connections') }}" class="mt-4 inline-flex items-center text-indigo-600 text-sm font-medium hover:underline">
                Add a connection
            </a>
        </div>
    @else
        <div class="grid md:grid-cols-3 gap-4">
            @foreach($providerTotals as $total)
                <div class="bg-white rounded-lg border border-slate-200 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-800">{{ $total['provider_name'] }}</p>
                        <span class="text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{{ $total['accounts'] }} account{{ $total['accounts'] === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-slate-800">{{ $total['published'] }}</p>
                            <p class="text-xs text-slate-400">Published</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-indigo-600">{{ $total['scheduled'] }}</p>
                            <p class="text-xs text-slate-400">Scheduled</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold {{ $total['failed'] ? 'text-red-600' : 'text-slate-800' }}">{{ $total['failed'] }}</p>
                            <p class="text-xs text-slate-400">Failed</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="space-y-5">
        @foreach($accountSummaries as $summary)
            @php
                $account = $summary['account'];
                $isLinkedIn = $summary['provider_key'] === 'linkedin';
                $isWordPress = $summary['provider_key'] === 'wordpress';
            @endphp

            <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold
                            {{ $isLinkedIn ? 'bg-blue-100 text-blue-700' : ($isWordPress ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600') }}">
                            {{ $isWordPress ? 'W' : strtoupper(substr($account->provider?->name ?? 'S', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $account->display_name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ $account->provider?->name }} @if($account->public_profile_url) · {{ $account->public_profile_url }} @endif</p>
                        </div>
                    </div>
                    @if($account->public_profile_url)
                        <a href="{{ $account->public_profile_url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline flex-shrink-0">Open</a>
                    @endif
                </div>

                <div class="grid md:grid-cols-4 gap-4 p-5">
                    @if($isLinkedIn)
                        <div class="border border-slate-200 rounded-lg p-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Followers</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $summary['follower_count'] !== null ? number_format($summary['follower_count']) : '—' }}</p>
                        </div>
                        @foreach(['impressionCount' => 'Impressions', 'likeCount' => 'Likes', 'clickCount' => 'Clicks'] as $key => $label)
                            <div class="border border-slate-200 rounded-lg p-4">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $label }}</p>
                                <p class="text-2xl font-bold text-slate-800 mt-1">{{ isset($summary['aggregate'][$key]) ? number_format($summary['aggregate'][$key]) : '—' }}</p>
                            </div>
                        @endforeach
                    @else
                        <div class="border border-slate-200 rounded-lg p-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Published</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $summary['published_count'] }}</p>
                        </div>
                        <div class="border border-slate-200 rounded-lg p-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Scheduled</p>
                            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $summary['scheduled_count'] }}</p>
                        </div>
                        <div class="border border-slate-200 rounded-lg p-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Failed</p>
                            <p class="text-2xl font-bold {{ $summary['failed_count'] ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $summary['failed_count'] }}</p>
                        </div>
                        <div class="border border-slate-200 rounded-lg p-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</p>
                            <p class="text-sm font-semibold text-green-700 mt-2">{{ ucfirst($account->status) }}</p>
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-100">
                    <div class="px-5 py-3 bg-slate-50">
                        <h3 class="text-sm font-semibold text-slate-700">Recent Activity</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($summary['recent_targets'] as $target)
                            <div class="px-5 py-3 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">{{ $target->post?->title_internal }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Published {{ $target->published_at?->diffForHumans() }}</p>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    @if($target->remote_post_url)
                                        <a href="{{ $target->remote_post_url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">Open</a>
                                    @endif
                                    @if($target->post)
                                        <a href="{{ route('social-studio.posts.show', $target->post->id) }}" class="text-xs text-slate-500 hover:underline">CRM</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-slate-500">No published activity yet.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endforeach
    </div>

</div>
@endsection
