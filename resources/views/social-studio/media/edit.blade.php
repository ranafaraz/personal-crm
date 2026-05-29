@extends('layouts.app')
@section('title', 'Edit Media Asset')

@section('content')
<div class="p-6 max-w-xl space-y-5">

    <div>
        <a href="{{ route('social-studio.media.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Media Library</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Edit Media Asset</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <img src="{{ $asset->storageUrl() }}" alt="{{ $asset->alt_text }}"
             class="w-full max-h-64 object-contain rounded-lg bg-slate-50">
        <p class="text-xs text-slate-500 mt-2">{{ $asset->original_name }} &middot; {{ number_format($asset->file_size_bytes / 1024, 1) }} KB</p>
    </div>

    <form method="POST" action="{{ route('social-studio.media.update', $asset->id) }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Alt Text <span class="text-red-500">*</span></label>
                <input type="text" name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" required maxlength="500"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $asset->title) }}" maxlength="255"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Rights Status</label>
                <select name="rights_status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    @foreach(['owned' => 'Owned', 'licensed' => 'Licensed', 'generated' => 'AI Generated', 'unknown' => 'Unknown'] as $val => $label)
                        <option value="{{ $val }}" {{ old('rights_status', $asset->rights_status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Source Notes</label>
                <textarea name="source_notes" rows="2" maxlength="1000"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ old('source_notes', $asset->source_notes) }}</textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save Changes
            </button>
            <a href="{{ route('social-studio.media.index') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
