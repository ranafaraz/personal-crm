@extends('layouts.app')
@section('title', 'Tenants')
@section('page-title', 'Tenants')

@section('header-actions')
    <a href="{{ route('admin.tenants.create') }}"
       class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Tenant
    </a>
@endsection

@section('content')
{{-- Filters --}}
<form method="GET" class="flex gap-3 mb-5">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, email, slug…"
           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All statuses</option>
        @foreach (['active','trial','suspended','cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <select name="plan" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All plans</option>
        @foreach (['free','pro','enterprise'] as $p)
            <option value="{{ $p }}" @selected(request('plan') === $p)>{{ ucfirst($p) }}</option>
        @endforeach
    </select>
    <button class="bg-gray-700 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-800">Filter</button>
    @if(request()->hasAny(['q','status','plan']))
        <a href="{{ route('admin.tenants.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg">Clear</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="text-left px-5 py-3">Tenant</th>
                <th class="text-left px-5 py-3">Plan</th>
                <th class="text-left px-5 py-3">Status</th>
                <th class="text-right px-5 py-3">Users</th>
                <th class="text-left px-5 py-3">Trial ends</th>
                <th class="text-left px-5 py-3">Created</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($tenants as $tenant)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="font-medium text-gray-900">{{ $tenant->name }}</div>
                    <div class="text-xs text-gray-400">{{ $tenant->email ?? $tenant->slug }}</div>
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $tenant->planLabel() }}</td>
                <td class="px-5 py-3">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $tenant->statusBadge() }}">
                        {{ ucfirst($tenant->status) }}
                    </span>
                </td>
                <td class="px-5 py-3 text-right text-gray-600">{{ $tenant->users_count }}/{{ $tenant->max_users }}</td>
                <td class="px-5 py-3 text-gray-500">
                    {{ $tenant->trial_ends_at ? $tenant->trial_ends_at->format('M d, Y') : '—' }}
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $tenant->created_at->format('M d, Y') }}</td>
                <td class="px-5 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-indigo-600 hover:underline text-xs">Manage</a>
                        <a href="{{ route('admin.tenants.edit', $tenant) }}" class="text-gray-500 hover:underline text-xs">Edit</a>
                        @if ($tenant->status === 'suspended')
                            <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">
                                @csrf @method('PATCH')
                                <button class="text-green-600 hover:underline text-xs">Activate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}">
                                @csrf @method('PATCH')
                                <button class="text-red-500 hover:underline text-xs">Suspend</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No tenants found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($tenants->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $tenants->links() }}</div>
    @endif
</div>
@endsection
