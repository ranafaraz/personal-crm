@extends('layouts.app')
@section('title', 'Media Library')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">Media Library</h1>
        <a href="{{ route('social-studio.media.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Upload Image
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- Filter --}}
    <div class="flex gap-2">
        @foreach(['all' => 'All', 'approved' => 'Approved', 'pending_review' => 'Pending', 'rejected' => 'Rejected'] as $key => $label)
        <a href="{{ route('social-studio.media.index', ['status' => $key]) }}"
           class="text-xs font-medium px-3 py-1.5 rounded-full border transition
                  {{ $status === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300 hover:border-indigo-400' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($assets as $asset)
        <div class="group relative bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="aspect-square bg-slate-100">
                <img src="{{ $asset->storageUrl() }}" alt="{{ $asset->alt_text }}"
                     class="w-full h-full object-cover">
            </div>
            <div class="p-2">
                <p class="text-xs font-medium text-slate-700 truncate">{{ $asset->title ?: $asset->original_name }}</p>
                <p class="text-[10px] text-slate-400 truncate">{{ $asset->alt_text }}</p>
                <span class="inline-block mt-1 text-[10px] font-semibold rounded-full px-1.5 py-0.5
                    {{ $asset->approval_status === 'approved' ? 'bg-green-100 text-green-700' : ($asset->approval_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                    {{ ucfirst($asset->approval_status) }}
                </span>
            </div>
            <div class="absolute top-1 right-1 hidden group-hover:flex gap-1">
                <a href="{{ route('social-studio.media.edit', $asset->id) }}"
                   class="bg-white rounded p-1 shadow text-slate-500 hover:text-indigo-600">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
                <form method="POST" action="{{ route('social-studio.media.destroy', $asset->id) }}"
                      onsubmit="return confirm('Delete this image permanently?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-white rounded p-1 shadow text-slate-500 hover:text-red-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-16 text-slate-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm font-medium">No media assets yet.</p>
            <a href="{{ route('social-studio.media.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Upload your first image</a>
        </div>
        @endforelse
    </div>

    {{ $assets->withQueryString()->links() }}

</div>
@endsection
