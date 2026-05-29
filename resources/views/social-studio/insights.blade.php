@extends('layouts.app')
@section('title', 'LinkedIn Insights')

@section('content')
<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">LinkedIn Insights</h1>
            <p class="text-sm text-slate-500 mt-1">Analytics for your connected LinkedIn accounts</p>
        </div>
        <form method="POST" action="#" id="sync-form">
            @csrf
        </form>
    </div>

    @if(! $hasData)
        <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-slate-500 text-sm">No analytics data yet. Connect a LinkedIn account and analytics will sync automatically.</p>
            <a href="{{ route('social-studio.connections') }}" class="mt-4 inline-flex items-center gap-2 text-indigo-600 text-sm font-medium hover:underline">
                Connect LinkedIn account
            </a>
        </div>
    @endif

    @foreach($accountSummaries as $summary)
        @php $account = $summary['account']; @endphp

        <div class="space-y-4">

            {{-- Account header --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ $account->display_name }}</p>
                    @if($account->public_profile_url)
                        <a href="{{ $account->public_profile_url }}" target="_blank" rel="noopener"
                           class="text-xs text-indigo-500 hover:underline">View profile</a>
                    @endif
                </div>
            </div>

            {{-- Follower + aggregate stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Followers</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">
                        {{ $summary['follower_count'] !== null ? number_format($summary['follower_count']) : '—' }}
                    </p>
                </div>
                @foreach(['impressionCount' => 'Impressions', 'likeCount' => 'Likes', 'clickCount' => 'Clicks'] as $key => $label)
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $label }} (30d)</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1">
                            {{ isset($summary['aggregate'][$key]) ? number_format($summary['aggregate'][$key]) : '—' }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Recent posts table --}}
            @if($summary['recent_posts']->isNotEmpty())
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-700">Recent Published Posts</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($summary['recent_posts'] as $item)
                            @php $post = $item['post']; $metrics = $item['metrics']; @endphp
                            <div class="px-5 py-3 flex items-start gap-4">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">
                                        {{ $post->title_internal ?: Str::limit($post->post_body, 60) }}
                                    </p>
                                    <div class="flex items-center gap-4 mt-1">
                                        <span class="text-xs text-slate-400">{{ $post->updated_at->diffForHumans() }}</span>
                                        @if($post->linkedin_post_url)
                                            <a href="{{ $post->linkedin_post_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-500 hover:underline">View on LinkedIn</a>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-6 text-center flex-shrink-0">
                                    @foreach(['impressionCount' => 'Impr.', 'likeCount' => 'Likes', 'clickCount' => 'Clicks', 'commentCount' => 'Comments'] as $key => $label)
                                        <div>
                                            <p class="text-xs text-slate-400">{{ $label }}</p>
                                            <p class="text-sm font-semibold text-slate-700">
                                                {{ isset($metrics[$key]) ? number_format($metrics[$key]) : '—' }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                                <a href="{{ route('social-studio.posts.show', $post->id) }}"
                                   class="text-xs text-slate-400 hover:text-slate-700 flex-shrink-0 self-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white rounded-xl border border-slate-200 p-6 text-center text-sm text-slate-400">
                    No published posts with analytics yet.
                </div>
            @endif

        </div>

        @if(! $loop->last)
            <hr class="border-slate-200">
        @endif
    @endforeach

    {{-- Sync note --}}
    @if($hasData)
        <p class="text-xs text-slate-400 text-center">
            Analytics sync automatically every hour. Historical data refreshes daily at 03:00 UTC.
        </p>
    @endif

</div>
@endsection
