@extends('layouts.app')

@section('title', $document->name)
@section('page-title', $document->name)

@section('content')
<div class="max-w-3xl">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('documents.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Documents</a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-6">

        {{-- Header row --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $document->name }}</h2>
                @if($document->document_type)
                    <span class="inline-block mt-1 text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full px-2.5 py-0.5">
                        {{ ucfirst(str_replace('_', ' ', $document->document_type)) }}
                    </span>
                @endif
            </div>
            <div class="flex gap-2 shrink-0">
                <a href="{{ route('documents.download', $document->id) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                    Download
                </a>
                <form method="POST" action="{{ route('documents.destroy', $document->id) }}"
                      onsubmit="return confirm('Delete this document? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium px-4 py-2 rounded-lg border border-red-200 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>

        {{-- Metadata grid --}}
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-slate-500">File name</dt>
                <dd class="mt-0.5 text-slate-800 break-all">{{ $document->file_name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-500">File size</dt>
                <dd class="mt-0.5 text-slate-800">
                    @php
                        $bytes = $document->file_size ?? 0;
                        if ($bytes >= 1048576) {
                            echo number_format($bytes / 1048576, 2) . ' MB';
                        } elseif ($bytes >= 1024) {
                            echo number_format($bytes / 1024, 1) . ' KB';
                        } else {
                            echo $bytes . ' B';
                        }
                    @endphp
                </dd>
            </div>
            <div>
                <dt class="font-medium text-slate-500">MIME type</dt>
                <dd class="mt-0.5 text-slate-800">{{ $document->mime_type ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-500">Uploaded</dt>
                <dd class="mt-0.5 text-slate-800">{{ $document->created_at->format('M j, Y g:i A') }}</dd>
            </div>

            @if($document->opportunity)
            <div>
                <dt class="font-medium text-slate-500">Opportunity</dt>
                <dd class="mt-0.5">
                    <a href="{{ route('opportunities.show', $document->opportunity->id) }}"
                       class="text-indigo-600 hover:text-indigo-800">
                        {{ $document->opportunity->title }}
                    </a>
                </dd>
            </div>
            @endif

            @if($document->contact)
            <div>
                <dt class="font-medium text-slate-500">Contact</dt>
                <dd class="mt-0.5">
                    <a href="{{ route('contacts.show', $document->contact->id) }}"
                       class="text-indigo-600 hover:text-indigo-800">
                        {{ trim(($document->contact->first_name ?? '') . ' ' . ($document->contact->last_name ?? '')) ?: $document->contact->email }}
                    </a>
                </dd>
            </div>
            @endif
        </dl>

        @if($document->description)
        <div>
            <h3 class="text-sm font-medium text-slate-500 mb-1">Description</h3>
            <p class="text-sm text-slate-800 whitespace-pre-line">{{ $document->description }}</p>
        </div>
        @endif

    </div>
</div>
@endsection
