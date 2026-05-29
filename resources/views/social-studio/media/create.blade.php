@extends('layouts.app')
@section('title', 'Upload Media')

@section('content')
<div class="p-6 max-w-xl space-y-5">

    <div>
        <a href="{{ route('social-studio.media.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Media Library</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Upload Image</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.media.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Image File <span class="text-red-500">*</span></label>
                <input type="file" name="file" required accept="image/jpeg,image/png,image/gif,image/webp"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                <p class="text-xs text-slate-400 mt-1">JPG, PNG, GIF, WebP — max 10 MB</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Alt Text <span class="text-red-500">*</span></label>
                <input type="text" name="alt_text" value="{{ old('alt_text') }}" required maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="Describe the image for accessibility and LinkedIn">
                <p class="text-xs text-slate-400 mt-1">Required by LinkedIn for image posts.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Title (optional)</label>
                <input type="text" name="title" value="{{ old('title') }}" maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="Internal label for this image">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Rights Status</label>
                <select name="rights_status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="owned" {{ old('rights_status','owned') === 'owned' ? 'selected' : '' }}>Owned (I created this)</option>
                    <option value="licensed" {{ old('rights_status') === 'licensed' ? 'selected' : '' }}>Licensed</option>
                    <option value="generated" {{ old('rights_status') === 'generated' ? 'selected' : '' }}>AI Generated</option>
                    <option value="unknown" {{ old('rights_status') === 'unknown' ? 'selected' : '' }}>Unknown</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Source Notes</label>
                <textarea name="source_notes" rows="2" maxlength="1000"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                          placeholder="License URL, source, notes...">{{ old('source_notes') }}</textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Upload
            </button>
            <a href="{{ route('social-studio.media.index') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
