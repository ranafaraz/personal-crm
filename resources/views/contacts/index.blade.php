@extends('layouts.app')

@section('title', 'Contacts')
@section('page-title', 'Contacts')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" action="{{ route('contacts.index') }}" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, company..." class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="suppressed" {{ request('status') === 'suppressed' ? 'selected' : '' }}>Suppressed</option>
            <option value="bounced" {{ request('status') === 'bounced' ? 'selected' : '' }}>Bounced</option>
        </select>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Search</button>
        @if(request('search') || request('status'))
            <a href="{{ route('contacts.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">Clear</a>
        @endif
    </form>
    <div class="flex gap-2">
        <a href="{{ route('imports.create') }}" class="inline-flex items-center gap-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import
        </a>
        <a href="{{ route('contacts.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Contact
        </a>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-12">#</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">ID</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Company</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Job Title</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Contacted</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($contacts ?? [] as $contact)
                    @php
                        $statusBadge = match($contact->status) {
                            'active'     => 'bg-green-100 text-green-700',
                            'suppressed' => 'bg-red-100 text-red-700',
                            'bounced'    => 'bg-orange-100 text-orange-700',
                            default      => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('contacts.show', $contact) }}'">
                        <td class="px-4 py-3.5 text-xs text-slate-400">{{ ($contacts->currentPage() - 1) * $contacts->perPage() + $loop->iteration }}</td>
                        <td class="px-4 py-3.5 text-xs font-mono text-slate-500">#{{ $contact->id }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center text-xs font-bold text-indigo-700 flex-shrink-0">
                                    {{ strtoupper(substr($contact->first_name ?? 'C', 0, 1)) }}
                                </div>
                                <span class="font-medium text-slate-800">{{ $contact->first_name }} {{ $contact->last_name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $contact->email }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $contact->company ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $contact->job_title ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusBadge }}">{{ ucfirst($contact->status ?? 'active') }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500">
                            {{ $contact->last_contacted_at ? $contact->last_contacted_at->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-5 py-3.5" onclick="event.stopPropagation()">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('contacts.edit', $contact) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                <a href="{{ route('compose', ['contact_id' => $contact->id]) }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Email</a>
                                <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Delete this contact?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <p class="text-slate-500 text-sm font-medium">No contacts found</p>
                                <a href="{{ route('contacts.create') }}" class="text-indigo-600 text-sm hover:underline">Add your first contact</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($contacts) && $contacts->hasPages())
        <div class="px-5 py-4 border-t border-slate-200">
            {{ $contacts->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
