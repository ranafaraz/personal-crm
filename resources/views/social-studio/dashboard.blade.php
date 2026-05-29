@extends('layouts.app')
@section('title', 'Social Studio')

@section('content')
<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Social Studio</h1>
            <p class="text-sm text-slate-500 mt-1">Manage and publish your LinkedIn content</p>
        </div>
        <a href="{{ route('social-studio.posts.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Draft
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Drafts / In Review</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $draftsCount }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Scheduled (next 7 days)</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $scheduledCount }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Failed</p>
            <p class="text-3xl font-bold {{ $failedCount > 0 ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $failedCount }}</p>
        </div>
    </div>

    {{-- LinkedIn Connection Status --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">LinkedIn Connection</h2>
        @if($linkedInAccount && $linkedInAccount->status === 'connected')
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-700" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14m-.5 15.5v-5.3a3.26 3.26 0 00-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 011.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 001.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 00-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $linkedInAccount->display_name }}</p>
                        <p class="text-xs text-green-600 font-medium">Connected</p>
                    </div>
                </div>
                <a href="{{ route('social-studio.connections') }}" class="text-xs text-slate-500 hover:text-slate-700 underline">Manage</a>
            </div>
        @else
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">No LinkedIn account connected.</p>
                <a href="{{ route('social-studio.connections') }}"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                    Connect LinkedIn
                </a>
            </div>
        @endif
    </div>

    {{-- Last Published --}}
    @if($lastPublished)
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Last Published</h2>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-800">{{ $lastPublished->post->title_internal }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $lastPublished->published_at?->diffForHumans() }} via {{ $lastPublished->provider_key }}
                </p>
            </div>
            <a href="{{ route('social-studio.posts.show', $lastPublished->social_post_id) }}"
               class="text-xs text-indigo-600 hover:underline">View</a>
        </div>
    </div>
    @endif

    {{-- Coming-soon platforms --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Coming Soon</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($providers->where('status', 'coming_soon') as $provider)
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100 rounded-full px-3 py-1.5">
                    {{ $provider->name }}
                    <span class="text-[10px] bg-slate-200 rounded-full px-1.5 py-0.5 font-semibold">Soon</span>
                </span>
            @endforeach
        </div>
    </div>

</div>
@endsection
