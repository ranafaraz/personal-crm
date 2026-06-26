@extends('layouts.app')
@section('title', 'Documents')
@section('page-title', 'Documents')
@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Documents']]" />
@endsection
@section('content')
<div>
    <div class="flex items-center justify-between mb-5">
        <form method="GET" action="{{ route('documents.index') }}" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search documents..." class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">
            <select name="type" class="px-2.5 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                @foreach(['resume','cover_letter','proposal','portfolio','reference','other'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$t)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg">Search</button>
        </form>
        <a href="{{ route('documents.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Upload Document
        </a>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($documents->isEmpty())
            <div class="text-center py-16">
                <svg class="mx-auto w-12 h-12 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <p class="text-slate-500 font-medium">No documents uploaded yet.</p>
                <a href="{{ route('documents.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Upload your first document</a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Size</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Linked To</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Uploaded</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($documents as $doc)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $doc->name }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">{{ ucwords(str_replace('_',' ',$doc->document_type)) }}</span></td>
                        <td class="px-4 py-3 text-slate-500">{{ $doc->human_file_size }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($doc->opportunity) <a href="{{ route('opportunities.show', $doc->opportunity) }}" class="text-indigo-600 hover:underline">{{ Str::limit($doc->opportunity->title, 30) }}</a>
                            @elseif($doc->contact) <a href="{{ route('contacts.show', $doc->contact) }}" class="text-indigo-600 hover:underline">{{ $doc->contact->full_name }}</a>
                            @else <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $doc->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                            <a href="{{ route('documents.view', $doc) }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline px-2 py-1 rounded hover:bg-slate-100">View</a>
                            <a href="{{ route('documents.download', $doc) }}" class="text-xs text-slate-500 hover:underline px-2 py-1 rounded hover:bg-slate-100">Download</a>
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Delete this document?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($documents->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $documents->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
