@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('page-title', 'Platform Overview')

@section('header-actions')
    <a href="{{ route('admin.tenants.create') }}"
       class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Tenant
    </a>
@endsection

@section('content')
{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    @foreach ([
        ['Total Tenants',   $stats['total_tenants'],  'bg-indigo-50',  'text-indigo-700'],
        ['Active',          $stats['active_tenants'], 'bg-green-50',   'text-green-700'],
        ['On Trial',        $stats['trial_tenants'],  'bg-yellow-50',  'text-yellow-700'],
        ['Suspended',       $stats['suspended'],       'bg-red-50',     'text-red-700'],
        ['Pro / Enterprise',$stats['pro_plus'],        'bg-purple-50',  'text-purple-700'],
        ['Total Users',     $stats['total_users'],     'bg-blue-50',    'text-blue-700'],
    ] as [$label, $value, $bg, $fg])
    <div class="rounded-xl p-4 {{ $bg }}">
        <div class="text-2xl font-bold {{ $fg }}">{{ $value }}</div>
        <div class="text-xs font-medium text-gray-500 mt-0.5">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- Recent Tenants --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Recent Tenants</h2>
        <a href="{{ route('admin.tenants.index') }}" class="text-xs text-indigo-600 hover:underline">View all →</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="text-left px-5 py-3">Tenant</th>
                <th class="text-left px-5 py-3">Plan</th>
                <th class="text-left px-5 py-3">Status</th>
                <th class="text-right px-5 py-3">Users</th>
                <th class="text-left px-5 py-3">Created</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($recentTenants as $tenant)
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
                <td class="px-5 py-3 text-right text-gray-600">{{ $tenant->users->count() }}/{{ $tenant->max_users }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $tenant->created_at->format('M d, Y') }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-indigo-600 hover:underline text-xs">Manage</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No tenants yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
