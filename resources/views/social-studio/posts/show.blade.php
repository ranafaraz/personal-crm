@extends('layouts.app')
@section('title', $post->title_internal)
@section('page-title', Str::limit($post->title_internal, 60))
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Social Studio', 'url' => route('social-studio.dashboard')],
        ['label' => 'Content', 'url' => route('social-studio.posts.index')],
        ['label' => Str::limit($post->title_internal, 40)],
    ]" />
@endsection

@section('content')
<div class="p-6 max-w-3xl space-y-5">

    <div class="flex items-center justify-between">
        <a href="{{ route('social-studio.posts.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Posts</a>
        <div class="flex gap-2">
            @if($post->isEditable())
                <a href="{{ route('social-studio.posts.edit', $post->id) }}"
                   class="text-xs bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium px-3 py-1.5 rounded-lg transition">Edit</a>
            @endif
            <form method="POST" action="{{ route('social-studio.posts.destroy', $post->id) }}"
                  onsubmit="return confirm('Delete this post?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs bg-white border border-red-300 hover:bg-red-50 text-red-600 font-medium px-3 py-1.5 rounded-lg transition">Delete</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- Post card --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <h1 class="text-lg font-bold text-slate-800">{{ $post->title_internal }}</h1>
            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <span class="text-xs font-semibold rounded-full px-2.5 py-1
                    @php echo match($post->status) {
                        'draft'           => 'bg-slate-100 text-slate-600',
                        'ready_for_review'=> 'bg-yellow-100 text-yellow-700',
                        'approved'        => 'bg-blue-100 text-blue-700',
                        'scheduled'       => 'bg-indigo-100 text-indigo-700',
                        'publishing'      => 'bg-purple-100 text-purple-700',
                        'published'       => 'bg-green-100 text-green-700',
                        'failed'          => 'bg-red-100 text-red-700',
                        default           => 'bg-slate-100 text-slate-500',
                    }; @endphp">
                    {{ str_replace('_', ' ', ucfirst($post->status)) }}
                </span>
                <span class="text-xs rounded-full px-2.5 py-1
                    @php echo match($post->approval_status) {
                        'approved'       => 'bg-green-100 text-green-700',
                        'rejected'       => 'bg-red-100 text-red-700',
                        default          => 'bg-orange-100 text-orange-700',
                    }; @endphp">
                    {{ str_replace('_', ' ', ucfirst($post->approval_status)) }}
                </span>
            </div>
        </div>

        <div class="prose prose-sm max-w-none text-slate-700 border border-slate-100 rounded-lg p-4 bg-slate-50">{!! $post->post_body !!}</div>

        @if($post->hashtagString())
            <p class="text-sm text-blue-600">{{ $post->hashtagString() }}</p>
        @endif

        @if($post->article_url)
            <p class="text-xs"><span class="font-medium text-slate-600">URL:</span>
                <a href="{{ $post->article_url }}" target="_blank" class="text-indigo-600 hover:underline">{{ $post->article_url }}</a>
            </p>
        @endif

        <div class="grid grid-cols-2 gap-3 text-xs text-slate-500">
            <div><span class="font-medium text-slate-600">Type:</span> {{ strtoupper($post->post_type) }}</div>
            <div><span class="font-medium text-slate-600">Source:</span> {{ ucfirst($post->created_source) }}</div>
            @if($post->topic)
                <div><span class="font-medium text-slate-600">Topic:</span> {{ $post->topic }}</div>
            @endif
            @if($post->scheduled_at)
                <div><span class="font-medium text-slate-600">Scheduled:</span>
                    {{ $post->scheduled_at->setTimezone($post->timezone_display)->format('d M Y H:i') }} {{ $post->timezone_display }}
                </div>
            @endif
        </div>

        @if($post->mediaAssets->count())
            <div>
                <p class="text-xs font-medium text-slate-600 mb-2">Media</p>
                <div class="flex gap-2 flex-wrap">
                    @foreach($post->mediaAssets as $asset)
                        <img src="{{ $asset->storageUrl() }}" alt="{{ $asset->alt_text }}"
                             class="w-24 h-24 object-cover rounded-lg border border-slate-200">
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-slate-700">Actions</h2>
        <div class="flex flex-wrap gap-2">

            @if($post->status === 'draft')
                <form method="POST" action="{{ route('social-studio.posts.submit-for-review', $post->id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-medium px-4 py-2 rounded-lg transition">
                        Submit for Review
                    </button>
                </form>
            @endif

            @if(in_array($post->status, ['draft', 'ready_for_review']) && $post->approval_status !== 'approved')
                <form method="POST" action="{{ route('social-studio.posts.approve', $post->id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="text-sm bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition">
                        Approve
                    </button>
                </form>
                <form method="POST" action="{{ route('social-studio.posts.reject', $post->id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="text-sm bg-white border border-red-300 hover:bg-red-50 text-red-600 font-medium px-4 py-2 rounded-lg transition">
                        Reject
                    </button>
                </form>
            @endif

            @if($post->isApproved() && ! $post->scheduled_at)
                <form method="POST" action="{{ route('social-studio.posts.schedule', $post->id) }}" class="flex gap-2 items-end">
                    @csrf @method('PATCH')
                    <input type="datetime-local" name="scheduled_at" required
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <input type="text" name="timezone_display" value="{{ auth()->user()->timezone ?? 'Asia/Karachi' }}"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-36 focus:ring-2 focus:ring-indigo-500 outline-none"
                           placeholder="Timezone">
                    <button type="submit" class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                        Schedule
                    </button>
                </form>
            @endif

            @if($post->isApproved() && $post->scheduled_at)
                <form method="POST" action="{{ route('social-studio.posts.cancel-schedule', $post->id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="text-sm bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium px-4 py-2 rounded-lg transition">
                        Cancel Schedule
                    </button>
                </form>
            @endif

            @php
                $unpublishedTargets = $post->targets->where('status', '!=', 'published');
                $retryMode = $post->status === 'failed' && $unpublishedTargets->isNotEmpty();
                $confirmMsg = $retryMode
                    ? 'Retry publishing to all failed targets?'
                    : 'Publish this post to all selected targets right now? This cannot be undone.';
            @endphp
            @if($post->isApproved() && $unpublishedTargets->isNotEmpty())
                <form method="POST" action="{{ route('social-studio.posts.publish-now', $post->id) }}"
                      onsubmit="return confirm('{{ $confirmMsg }}')">
                    @csrf
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="text-sm {{ $retryMode ? 'bg-orange-600 hover:bg-orange-700' : 'bg-blue-700 hover:bg-blue-800' }} text-white font-medium px-4 py-2 rounded-lg transition">
                        {{ $retryMode ? 'Retry Failed Targets' : 'Publish Now' }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Targets --}}
    @if($post->targets->count())
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Publish Targets</h2>
        @foreach($post->targets as $target)
        <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100 last:border-0">
            <div>
                <span class="font-medium text-slate-700 capitalize">{{ $target->provider_key }}</span>
                @if($target->account)
                    <span class="text-slate-400"> — {{ $target->account->display_name }}</span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs rounded-full px-2 py-0.5 font-medium
                    @php echo match($target->status) {
                        'published' => 'bg-green-100 text-green-700',
                        'failed'    => 'bg-red-100 text-red-700',
                        'scheduled' => 'bg-indigo-100 text-indigo-700',
                        default     => 'bg-slate-100 text-slate-600',
                    }; @endphp">
                    {{ ucfirst($target->status) }}
                </span>
                @if($target->error_message)
                    <span class="text-xs text-red-500" title="{{ $target->error_message }}">Error</span>
                @endif
                @if($target->remote_post_id && $target->remote_post_id !== 'unknown')
                    @if($target->remote_post_url)
                        <a href="{{ $target->remote_post_url }}" target="_blank" class="text-xs text-indigo-600 hover:underline">Open</a>
                    @endif
                    <span class="text-xs text-slate-400">ID: {{ $target->remote_post_id }}</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Source notes --}}
    @if($post->source_notes)
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Source Notes</h2>
        <p class="text-sm text-slate-600 whitespace-pre-wrap">{{ $post->source_notes }}</p>
    </div>
    @endif

</div>
@endsection
