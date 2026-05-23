@extends('layouts.app')

@section('title', 'Tags')
@section('page-title', 'Tags')

@section('content')
<div class="max-w-4xl space-y-5">
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <form method="POST" action="{{ route('tags.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                <input type="text" name="name" required value="{{ old('name') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Color</label>
                <input type="text" name="color" value="{{ old('color', '#4f46e5') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Add Tag</button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Tag</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Contacts</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Opportunities</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tags as $tag)
                    <tr>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-2 font-medium text-slate-800">
                                <span class="w-3 h-3 rounded-full" style="background: {{ $tag->color ?: '#4f46e5' }}"></span>
                                {{ $tag->name }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $tag->contacts_count }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $tag->opportunities_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('Delete this tag?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">No tags yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($tags->hasPages())
            <div class="px-5 py-4 border-t border-slate-200">{{ $tags->links() }}</div>
        @endif
    </div>
</div>
@endsection
