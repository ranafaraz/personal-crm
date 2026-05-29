@extends('layouts.app')
@section('title', 'Posts')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">Posts</h1>
        <a href="{{ route('social-studio.posts.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Draft
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- Status tabs --}}
    <div class="flex gap-2 flex-wrap">
        @php
            $tabs = ['all' => 'All', 'draft' => 'Draft', 'ready_for_review' => 'In Review', 'approved' => 'Approved', 'scheduled' => 'Scheduled', 'published' => 'Published', 'failed' => 'Failed'];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('social-studio.posts.index', ['status' => $key]) }}"
               class="text-xs font-medium px-3 py-1.5 rounded-full border transition
                      {{ $status === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300 hover:border-indigo-400' }}">
                {{ $label }}
                @if(isset($statusCounts[$key]))
                    <span class="ml-1 opacity-70">({{ $statusCounts[$key] }})</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Posts list --}}
    <div class="space-y-3">
        @forelse($posts as $post)
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('social-studio.posts.show', $post->id) }}"
                       class="text-sm font-semibold text-slate-800 hover:text-indigo-600 truncate">
                        {{ $post->title_internal }}
                    </a>
                    <span class="text-[11px] font-semibold rounded-full px-2 py-0.5
                        @php
                            echo match($post->status) {
                                'draft'           => 'bg-slate-100 text-slate-600',
                                'ready_for_review'=> 'bg-yellow-100 text-yellow-700',
                                'approved'        => 'bg-blue-100 text-blue-700',
                                'scheduled'       => 'bg-indigo-100 text-indigo-700',
                                'published'       => 'bg-green-100 text-green-700',
                                'failed'          => 'bg-red-100 text-red-700',
                                default           => 'bg-slate-100 text-slate-500',
                            };
                        @endphp">
                        {{ str_replace('_', ' ', ucfirst($post->status)) }}
                    </span>
                    @if($post->approval_status === 'pending_review')
                        <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-orange-100 text-orange-700">Pending Review</span>
                    @elseif($post->approval_status === 'approved')
                        <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-green-100 text-green-700">Approved</span>
                    @endif
                    <span class="text-[11px] text-slate-400">{{ strtoupper($post->post_type) }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ Str::limit($post->post_body, 120) }}</p>
                <div class="flex items-center gap-3 mt-2 text-xs text-slate-400">
                    @if($post->scheduled_at)
                        <span>Scheduled: {{ $post->scheduled_at->setTimezone($post->timezone_display)->format('d M Y H:i') }} {{ $post->timezone_display }}</span>
                    @endif
                    <span>Updated {{ $post->updated_at->diffForHumans() }}</span>
                    @foreach($post->targets as $target)
                        <span class="text-blue-500">{{ ucfirst($target->provider_key) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex-shrink-0 flex gap-2">
                <a href="{{ route('social-studio.posts.show', $post->id) }}"
                   class="text-xs text-indigo-600 hover:underline">View</a>
                @if($post->isEditable())
                    <a href="{{ route('social-studio.posts.edit', $post->id) }}"
                       class="text-xs text-slate-500 hover:underline">Edit</a>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center py-16 text-slate-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm font-medium">No posts found</p>
            <a href="{{ route('social-studio.posts.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Create your first draft</a>
        </div>
        @endforelse
    </div>

    {{ $posts->withQueryString()->links() }}

</div>
@endsection
