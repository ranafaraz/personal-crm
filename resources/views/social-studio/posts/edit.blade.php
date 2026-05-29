@extends('layouts.app')
@section('title', 'Edit Post')

@section('content')
<div class="p-6 max-w-3xl space-y-5">

    <div>
        <a href="{{ route('social-studio.posts.show', $post->id) }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Post</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Edit Draft</h1>
        @if($post->approval_status === 'approved')
            <div class="mt-2 bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs rounded-lg px-3 py-2">
                This post is already approved. Editing will reset approval to pending review.
            </div>
        @endif
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.posts.update', $post->id) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Post Details</h2>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Internal Title <span class="text-red-500">*</span></label>
                <input type="text" name="title_internal" value="{{ old('title_internal', $post->title_internal) }}" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Topic / Theme</label>
                <input type="text" name="topic" value="{{ old('topic', $post->topic) }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Post Type <span class="text-red-500">*</span></label>
                <select name="post_type" id="post_type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        onchange="togglePostTypeFields()">
                    <option value="text" {{ old('post_type', $post->post_type) === 'text' ? 'selected' : '' }}>Text Only</option>
                    <option value="image" {{ old('post_type', $post->post_type) === 'image' ? 'selected' : '' }}>Image</option>
                    <option value="article_link" {{ old('post_type', $post->post_type) === 'article_link' ? 'selected' : '' }}>Article / Link</option>
                </select>
            </div>

            <div id="article_url_field">
                <label class="block text-xs font-medium text-slate-700 mb-1">Article URL</label>
                <input type="url" name="article_url" value="{{ old('article_url', $post->article_url) }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="https://example.com/article">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Post Body <span class="text-red-500">*</span></label>
                <textarea name="post_body" rows="8" required maxlength="3000"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ old('post_body', $post->post_body) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Hashtags</label>
                <input type="text" name="hashtags" value="{{ old('hashtags', $post->hashtagString()) }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="#leadership #linkedin">
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Schedule</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_at"
                           value="{{ old('scheduled_at', $post->scheduled_at?->setTimezone($post->timezone_display)->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Timezone</label>
                    <input type="text" name="timezone_display" value="{{ old('timezone_display', $post->timezone_display) }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <label class="block text-xs font-medium text-slate-700 mb-1">Source Notes (Internal)</label>
            <textarea name="source_notes" rows="3"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ old('source_notes', $post->source_notes) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save Changes
            </button>
            <a href="{{ route('social-studio.posts.show', $post->id) }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function togglePostTypeFields() {
    const type = document.getElementById('post_type').value;
    document.getElementById('article_url_field').style.display = type === 'article_link' ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', togglePostTypeFields);
</script>
@endpush
@endsection
