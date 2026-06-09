@extends('layouts.app')

@section('title', 'Opportunities')
@section('page-title', 'Opportunities')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" action="{{ route('opportunities.index') }}" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search opportunities..." class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <select name="type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Types</option>
            <option value="job" {{ request('type') === 'job' ? 'selected' : '' }}>Job</option>
            <option value="scholarship" {{ request('type') === 'scholarship' ? 'selected' : '' }}>Scholarship</option>
            <option value="research" {{ request('type') === 'research' ? 'selected' : '' }}>Research</option>
            <option value="grant" {{ request('type') === 'grant' ? 'selected' : '' }}>Grant</option>
            <option value="networking" {{ request('type') === 'networking' ? 'selected' : '' }}>Networking</option>
        </select>
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="waiting_reply" {{ request('status') === 'waiting_reply' ? 'selected' : '' }}>Waiting Reply</option>
            <option value="replied" {{ request('status') === 'replied' ? 'selected' : '' }}>Replied</option>
            <option value="interview" {{ request('status') === 'interview' ? 'selected' : '' }}>Interview</option>
            <option value="offer" {{ request('status') === 'offer' ? 'selected' : '' }}>Offer</option>
            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
        <select name="priority" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Priorities</option>
            <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
            <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
            <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
        </select>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Filter</button>
        @if(request()->hasAny(['search', 'type', 'status', 'priority']))
            <a href="{{ route('opportunities.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">Clear</a>
        @endif
    </form>
    <div class="flex items-center gap-2">
        <a href="{{ route('opportunity-imports.template') }}" class="inline-flex items-center gap-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            CSV Template
        </a>
        <a href="{{ route('opportunity-imports.create') }}" class="inline-flex items-center gap-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import CSV
        </a>
        <a href="{{ route('opportunities.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Opportunity
        </a>
    </div>
</div>

<form method="POST" action="{{ route('opportunities.bulk-destroy') }}"
      x-data="{ selected: [], get allOnPage() { return Array.from(document.querySelectorAll('input[name=&quot;ids[]&quot;]')).map(el => el.value); } }"
      @submit="if (!selected.length || !confirm('Delete ' + selected.length + ' opportunity(ies)? This cannot be undone.')) $event.preventDefault()">
    @csrf
    @method('DELETE')

    {{-- Bulk action bar (only shows when something is selected) --}}
    <div x-show="selected.length > 0" x-cloak class="mb-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5 flex items-center justify-between">
        <p class="text-sm text-amber-800"><span x-text="selected.length"></span> selected</p>
        <div class="flex items-center gap-2">
            <button type="button" @click="selected = []" class="text-xs text-slate-600 hover:text-slate-800 font-medium px-3 py-1">Clear</button>
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3 py-1.5 rounded-md">Delete Selected</button>
        </div>
    </div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    <th class="px-3 py-3 w-10">
                        <input type="checkbox"
                               @change="selected = $event.target.checked ? allOnPage : []"
                               :checked="selected.length > 0 && selected.length === allOnPage.length"
                               aria-label="Select all on this page"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-12">#</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">ID</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Organization</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Deadline</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Activity</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($opportunities ?? [] as $opp)
                    @php
                        $typeColors = [
                            'job'        => 'bg-blue-100 text-blue-700',
                            'scholarship'=> 'bg-purple-100 text-purple-700',
                            'research'   => 'bg-indigo-100 text-indigo-700',
                            'grant'      => 'bg-yellow-100 text-yellow-700',
                            'networking' => 'bg-green-100 text-green-700',
                        ];
                        $statusColors = [
                            'draft'         => 'bg-slate-100 text-slate-600',
                            'active'        => 'bg-blue-100 text-blue-700',
                            'waiting_reply' => 'bg-indigo-100 text-indigo-700',
                            'replied'       => 'bg-teal-100 text-teal-700',
                            'interview'     => 'bg-yellow-100 text-yellow-700',
                            'offer'         => 'bg-green-100 text-green-700',
                            'rejected'      => 'bg-red-100 text-red-700',
                        ];
                        $priorityColors = [
                            'urgent' => 'bg-red-100 text-red-700',
                            'high'   => 'bg-orange-100 text-orange-700',
                            'medium' => 'bg-yellow-100 text-yellow-700',
                            'low'    => 'bg-slate-100 text-slate-600',
                        ];
                        $tc = $typeColors[$opp->type] ?? 'bg-slate-100 text-slate-600';
                        $sc = $statusColors[$opp->status] ?? 'bg-slate-100 text-slate-600';
                        $pc = $priorityColors[$opp->priority] ?? 'bg-slate-100 text-slate-600';
                        $isOverdue = $opp->deadline && $opp->deadline->isPast() && !in_array($opp->status, ['offer', 'rejected']);
                    @endphp
                    <tr class="hover:bg-slate-50 cursor-pointer {{ $isOverdue ? 'bg-red-50' : '' }}" onclick="window.location='{{ route('opportunities.show', $opp) }}'">
                        <td class="px-3 py-3.5" onclick="event.stopPropagation()">
                            <input type="checkbox" name="ids[]" value="{{ $opp->id }}" x-model="selected"
                                   aria-label="Select opportunity {{ $opp->id }}"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-4 py-3.5 text-xs text-slate-400">{{ ($opportunities->currentPage() - 1) * $opportunities->perPage() + $loop->iteration }}</td>
                        <td class="px-4 py-3.5 text-xs font-mono text-slate-500">#{{ $opp->id }}</td>
                        <td class="px-5 py-3.5 font-medium text-slate-800 max-w-xs">
                            <span class="truncate block">{{ $opp->title }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $tc }}">{{ ucfirst($opp->type) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $opp->organization ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $sc }}">{{ ucfirst(str_replace('_', ' ', $opp->status)) }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $pc }}">{{ ucfirst($opp->priority) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-xs {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-500' }}">
                            {{ $opp->deadline ? $opp->deadline->format('M d, Y') : '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500">
                            {{ $opp->updated_at ? $opp->updated_at->diffForHumans() : '—' }}
                        </td>
                        <td class="px-5 py-3.5" onclick="event.stopPropagation()">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('opportunities.edit', $opp) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                {{-- Per-row delete: can't be a nested <form> inside the bulk-delete <form>,
                                     so we submit via a one-off detached form built in JS. --}}
                                <button type="button"
                                        @click="if (confirm('Delete this opportunity?')) {
                                            const f = document.createElement('form');
                                            f.method = 'POST';
                                            f.action = '{{ route('opportunities.destroy', $opp) }}';
                                            const t = document.createElement('input');
                                            t.type = 'hidden'; t.name = '_token';
                                            t.value = document.querySelector('meta[name=csrf-token]').content;
                                            const m = document.createElement('input');
                                            m.type = 'hidden'; m.name = '_method'; m.value = 'DELETE';
                                            f.appendChild(t); f.appendChild(m);
                                            document.body.appendChild(f);
                                            f.submit();
                                        }"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <p class="text-slate-500 text-sm font-medium">No opportunities found</p>
                                <a href="{{ route('opportunities.create') }}" class="text-indigo-600 text-sm hover:underline">Add your first opportunity</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($opportunities) && $opportunities->hasPages())
        <div class="px-5 py-4 border-t border-slate-200">
            {{ $opportunities->withQueryString()->links() }}
        </div>
    @endif
</div>
</form>
@endsection
