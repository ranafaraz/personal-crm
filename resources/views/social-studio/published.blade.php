@extends('layouts.app')
@section('title', 'Published Posts')

@section('content')
<div class="p-6 space-y-5">

    <h1 class="text-2xl font-bold text-slate-800">Published</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    <div class="space-y-3">
        @forelse($published as $target)
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <a href="{{ route('social-studio.posts.show', $target->social_post_id) }}"
                       class="text-sm font-semibold text-slate-800 hover:text-indigo-600">
                        {{ $target->post->title_internal }}
                    </a>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ Str::limit($target->post->post_body, 100) }}</p>
                    <div class="flex gap-3 mt-2 text-xs text-slate-400">
                        <span class="capitalize">{{ $target->provider_key }}</span>
                        @if($target->account)
                            <span>{{ $target->account->display_name }}</span>
                        @endif
                        <span>Published {{ $target->published_at?->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    @if($target->remote_post_id && $target->remote_post_id !== 'unknown')
                        <span class="text-xs text-slate-400">ID: {{ Str::limit($target->remote_post_id, 30) }}</span>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-16 text-slate-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium">No published posts yet.</p>
        </div>
        @endforelse
    </div>

    {{ $published->links() }}

</div>
@endsection
