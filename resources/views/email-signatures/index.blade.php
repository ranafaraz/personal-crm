@extends('layouts.app')
@section('title', 'Email Signatures')
@section('page-title', 'Email Signatures')

@section('content')
<div>
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">{{ $signatures->total() }} signatures</p>
        <a href="{{ route('email-signatures.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Signature
        </a>
    </div>

    @if($signatures->isEmpty())
        <div class="bg-white border border-slate-200 rounded-xl text-center py-16">
            <p class="text-slate-500 font-medium">No email signatures yet.</p>
            <a href="{{ route('email-signatures.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Create your first signature</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($signatures as $signature)
                <div class="bg-white border border-slate-200 rounded-xl p-5 flex flex-col gap-3 hover:shadow-sm transition-shadow">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">{{ $signature->name }}</h3>
                            @if($signature->is_default)
                                <span class="mt-1 inline-block text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Default</span>
                            @endif
                        </div>
                        @if($signature->image_url)
                            <img src="{{ $signature->image_url }}" alt="{{ $signature->name }}" class="max-h-12 max-w-28 object-contain rounded border border-slate-200">
                        @endif
                    </div>

                    <div class="text-xs text-slate-600 line-clamp-4 leading-relaxed border-t border-slate-100 pt-3">
                        {!! $signature->body ?: '<span class="text-slate-400">Image-only signature</span>' !!}
                    </div>

                    <div class="flex gap-2 pt-1 border-t border-slate-100">
                        <a href="{{ route('email-signatures.edit', $signature) }}" class="text-xs text-slate-600 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-100 flex-1 text-center">Edit</a>
                        @unless($signature->is_default)
                            <form method="POST" action="{{ route('email-signatures.set-default', $signature) }}">
                                @csrf
                                <button type="submit" class="text-xs text-slate-600 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-100">Make Default</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('email-signatures.destroy', $signature) }}" onsubmit="return confirm('Delete this signature?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        @if($signatures->hasPages())
            <div class="mt-4">{{ $signatures->links() }}</div>
        @endif
    @endif
</div>
@endsection
