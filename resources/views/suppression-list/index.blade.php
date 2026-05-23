@extends('layouts.app')
@section('title', 'Suppression List')
@section('page-title', 'Suppression List')
@section('content')
<div class="max-w-3xl">
    {{-- Add Form --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Add Email to Suppression List</h2>
        <form method="POST" action="{{ route('suppression-list.store') }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" required value="{{ old('email') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56" placeholder="email@example.com">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
                <select name="reason" class="px-2.5 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach(['manual'=>'Manual','bounce'=>'Bounce','unsubscribe'=>'Unsubscribe','complaint'=>'Complaint','other'=>'Other'] as $val=>$label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                <input type="text" name="notes" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-40" placeholder="Optional notes">
            </div>
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Suppress</button>
        </form>
        @if($errors->any())
            <p class="text-sm text-red-600 mt-2">{{ $errors->first() }}</p>
        @endif
    </div>

    {{-- List --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($entries->isEmpty())
            <div class="text-center py-12">
                <p class="text-slate-500">No emails suppressed yet.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Added</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Notes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($entries as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-sm text-slate-800">{{ $item->email }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ ucfirst($item->reason) }}</span></td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $item->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $item->notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('suppression-list.destroy', $item) }}" onsubmit="return confirm('Remove from suppression list?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-green-600 hover:text-green-800 px-2 py-1 rounded hover:bg-green-50">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($entries->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $entries->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
