@extends('layouts.app')
@section('title', 'New Draft')

@section('content')
<div class="p-6 max-w-3xl space-y-5">

    <div>
        <a href="{{ route('social-studio.posts.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Posts</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">New Draft</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.posts.store') }}" class="space-y-5">
        @csrf

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Post Details</h2>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Internal Title <span class="text-red-500">*</span></label>
                <input type="text" name="title_internal" value="{{ old('title_internal') }}" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="e.g. LinkedIn post about Q2 results">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Topic / Theme</label>
                <input type="text" name="topic" value="{{ old('topic') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="e.g. Thought leadership, Product launch">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Post Type <span class="text-red-500">*</span></label>
                <select name="post_type" id="post_type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        onchange="togglePostTypeFields()">
                    <option value="text" {{ old('post_type','text') === 'text' ? 'selected' : '' }}>Text Only</option>
                    <option value="image" {{ old('post_type') === 'image' ? 'selected' : '' }}>Image</option>
                    <option value="article_link" {{ old('post_type') === 'article_link' ? 'selected' : '' }}>Article / Link</option>
                </select>
            </div>

            <div id="article_url_field" class="hidden">
                <label class="block text-xs font-medium text-slate-700 mb-1">Article URL <span class="text-red-500">*</span></label>
                <input type="url" name="article_url" value="{{ old('article_url') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="https://example.com/article">
            </div>

            <div id="image_field" class="hidden">
                <label class="block text-xs font-medium text-slate-700 mb-1">Featured Image (from Media Library)</label>
                @if($assets->count())
                    <select name="featured_asset_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">-- Select image --</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" {{ old('featured_asset_id') == $asset->id ? 'selected' : '' }}>
                                {{ $asset->title ?: $asset->original_name }} ({{ $asset->alt_text }})
                            </option>
                        @endforeach
                    </select>
                @else
                    <p class="text-xs text-slate-500">No approved images in your media library.
                        <a href="{{ route('social-studio.media.create') }}" class="text-indigo-600 hover:underline">Upload one first</a>.
                    </p>
                @endif
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Post Body <span class="text-red-500">*</span></label>
                <textarea name="post_body" rows="8" required maxlength="3000"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                          placeholder="Write your LinkedIn post here...">{{ old('post_body') }}</textarea>
                <p class="text-xs text-slate-400 mt-1">Max 3,000 characters. Hashtags are added separately below.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Hashtags</label>
                <input type="text" name="hashtags" value="{{ old('hashtags') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="#leadership #linkedin #productivity">
                <p class="text-xs text-slate-400 mt-1">Space or comma separated. The # symbol is optional.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Schedule (Optional)</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Date &amp; Time</label>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Timezone</label>
                    <input type="text" name="timezone_display" value="{{ old('timezone_display', 'Asia/Karachi') }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>
            <p class="text-xs text-slate-400">Scheduling a post does NOT publish it. You must approve it first.</p>
        </div>

        @if($linkedInAccount)
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-700">LinkedIn Settings</h2>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Visibility</label>
                <select name="visibility" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="PUBLIC" {{ old('visibility','PUBLIC') === 'PUBLIC' ? 'selected' : '' }}>Public (anyone)</option>
                    <option value="CONNECTIONS" {{ old('visibility') === 'CONNECTIONS' ? 'selected' : '' }}>Connections only</option>
                </select>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <label class="block text-xs font-medium text-slate-700 mb-1">Source Notes (Internal)</label>
            <textarea name="source_notes" rows="3"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                      placeholder="Research links, references, context...">{{ old('source_notes') }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save Draft
            </button>
            <a href="{{ route('social-studio.posts.index') }}"
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
    document.getElementById('article_url_field').classList.toggle('hidden', type !== 'article_link');
    document.getElementById('image_field').classList.toggle('hidden', type !== 'image');
}
document.addEventListener('DOMContentLoaded', togglePostTypeFields);
</script>
@endpush
@endsection
