@extends('layouts.app')
@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')
@section('content')
<div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($logs->isEmpty())
            <div class="text-center py-16">
                <p class="text-slate-500 font-medium">No audit log entries yet.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($logs as $activity)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">{{ $activity->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">{{ $activity->event ?? $activity->log_name ?? 'activity' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            @if($activity->subject_type)
                                {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                            @else —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 text-xs max-w-xs truncate">{{ $activity->description }}</td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $activity->properties['ip'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($logs->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $logs->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
