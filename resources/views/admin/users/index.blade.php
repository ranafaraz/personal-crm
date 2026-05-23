@extends('layouts.app')
@section('title', 'All Users')
@section('page-title', 'All Users')

@section('content')
<form method="GET" class="flex gap-3 mb-5">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…"
           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <button class="bg-gray-700 text-white text-sm px-4 py-2 rounded-lg hover:bg-gray-800">Search</button>
    @if(request('q'))
        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg">Clear</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="text-left px-5 py-3">User</th>
                <th class="text-left px-5 py-3">Tenant</th>
                <th class="text-left px-5 py-3">Role</th>
                <th class="text-left px-5 py-3">Status</th>
                <th class="text-left px-5 py-3">Joined</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                    <div class="text-xs text-gray-400">{{ $user->email }}</div>
                </td>
                <td class="px-5 py-3 text-gray-600">
                    @if ($user->tenant)
                        <a href="{{ route('admin.tenants.show', $user->tenant) }}" class="text-indigo-600 hover:underline">
                            {{ $user->tenant->name }}
                        </a>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-3">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $user->roleBadge() }}">
                        {{ $user->roleLabel() }}
                    </span>
                </td>
                <td class="px-5 py-3">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                <td class="px-5 py-3 text-right">
                    @if ($user->tenant)
                        <a href="{{ route('admin.tenants.show', $user->tenant) }}" class="text-xs text-indigo-600 hover:underline">
                            View tenant
                        </a>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($users->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $users->links() }}</div>
    @endif
</div>
@endsection
