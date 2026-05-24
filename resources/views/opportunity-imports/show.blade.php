@extends('layouts.app')
@section('title', 'Import Details')
@section('page-title', 'Opportunity Import Details')
@section('content')
<div class="max-w-4xl">
    <div class="mb-4"><a href="{{ route('opportunity-imports.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Imports</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-slate-700">{{ $import->file_name }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $import->created_at->format('M j, Y g:i A') }}</p>
            </div>
            @php
                $statusColors = ['pending'=>'yellow','parsing'=>'blue','parsed'=>'blue','processing'=>'blue','completed'=>'green','failed'=>'red'];
                $sc = $statusColors[$import->status] ?? 'gray';
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($import->status) }}</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div><p class="text-2xl font-bold text-slate-800">{{ $import->total_rows }}</p><p class="text-xs text-slate-500">Total Rows</p></div>
            <div><p class="text-2xl font-bold text-green-600">{{ $import->imported_rows }}</p><p class="text-xs text-slate-500">Imported</p></div>
            <div><p class="text-2xl font-bold text-yellow-500">{{ $import->skipped_rows }}</p><p class="text-xs text-slate-500">Skipped</p></div>
            <div><p class="text-2xl font-bold text-red-500">{{ $import->failed_rows }}</p><p class="text-xs text-slate-500">Failed</p></div>
        </div>
        @if($import->error_message)
            <div class="mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ $import->error_message }}</div>
        @endif
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Row</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Opportunity</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rows as $row)
                @php $rowColors = ['imported'=>'green','skipped'=>'yellow','failed'=>'red','pending'=>'gray']; $rc = $rowColors[$row->status] ?? 'gray'; @endphp
                <tr>
                    <td class="px-4 py-2 text-slate-500">#{{ $row->row_number }}</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $rc }}-100 text-{{ $rc }}-700">{{ ucfirst($row->status) }}</span></td>
                    <td class="px-4 py-2 text-slate-700 text-xs">
                        @if($row->opportunity)
                            <a href="{{ route('opportunities.show', $row->opportunity) }}" class="text-indigo-600 hover:underline">{{ $row->opportunity->title }}</a>
                        @else
                            {{ $row->raw_data['title'] ?? '—' }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs {{ $row->status === 'failed' ? 'text-red-500' : 'text-yellow-600' }}">{{ $row->error_message ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($rows->hasPages())
            <div class="px-4 py-3 border-t border-slate-200">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
