@extends('layouts.app')
@section('title', $tenant->name)
@section('page-title', $tenant->name)

@section('header-actions')
    <a href="{{ route('admin.tenants.edit', $tenant) }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-lg">
        Edit
    </a>
    @if ($tenant->status === 'suspended')
        <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}" class="inline">
            @csrf @method('PATCH')
            <button class="inline-flex items-center gap-1.5 text-sm text-white bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg">
                Activate
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="inline"
              onsubmit="return confirm('Suspend {{ $tenant->name }}?')">
            @csrf @method('PATCH')
            <button class="inline-flex items-center gap-1.5 text-sm text-white bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded-lg">
                Suspend
            </button>
        </form>
    @endif
@endsection

@section('content')
<div class="grid grid-cols-3 gap-6">

    {{-- Tenant info card --}}
    <div class="col-span-1 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 text-sm">
            <div class="flex items-center gap-2">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $tenant->statusBadge() }}">
                    {{ ucfirst($tenant->status) }}
                </span>
                <span class="text-gray-500">{{ $tenant->planLabel() }} plan</span>
            </div>
            <div class="space-y-2 text-gray-600">
                <div class="flex justify-between"><span class="text-gray-400">Slug</span><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $tenant->slug }}</code></div>
                <div class="flex justify-between"><span class="text-gray-400">Email</span>{{ $tenant->email ?? '—' }}</div>
                <div class="flex justify-between"><span class="text-gray-400">Max users</span>{{ $tenant->max_users }}</div>
                <div class="flex justify-between"><span class="text-gray-400">Users</span>{{ $users->count() }}</div>
                <div class="flex justify-between"><span class="text-gray-400">Trial ends</span>{{ $tenant->trial_ends_at ? $tenant->trial_ends_at->format('M d, Y') : '—' }}</div>
                <div class="flex justify-between"><span class="text-gray-400">Created</span>{{ $tenant->created_at->format('M d, Y') }}</div>
            </div>
            @if ($tenant->notes)
                <div class="pt-2 border-t border-gray-100 text-xs text-gray-500">{{ $tenant->notes }}</div>
            @endif
        </div>

        {{-- Danger zone --}}
        <div class="bg-white rounded-xl border border-red-200 p-5">
            <h3 class="text-xs font-semibold text-red-600 mb-3">Danger Zone</h3>
            <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}"
                  onsubmit="return confirm('Permanently delete {{ $tenant->name }}? This cannot be undone.')">
                @csrf @method('DELETE')
                <button class="w-full text-sm text-red-600 border border-red-300 hover:bg-red-50 px-3 py-2 rounded-lg">
                    Delete Tenant
                </button>
            </form>
        </div>
    </div>

    {{-- Users panel --}}
    <div class="col-span-2 space-y-4">

        {{-- Add user --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Add User</h2>
            <form method="POST" action="{{ route('admin.tenant-users.store', $tenant) }}" class="grid grid-cols-2 gap-3">
                @csrf
                <input type="text" name="name" placeholder="Full name" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <input type="email" name="email" placeholder="Email" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <input type="text" name="password" placeholder="Temp password (min 8 chars)" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <div class="flex gap-2">
                    <select name="role" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg">Add</button>
                </div>
            </form>
        </div>

        {{-- Users list --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">
                Users ({{ $users->count() }} / {{ $tenant->max_users }})
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-5 py-2">User</th>
                        <th class="text-left px-5 py-2">Role</th>
                        <th class="text-left px-5 py-2">Status</th>
                        <th class="text-left px-5 py-2">Joined</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $user->email }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.tenant-users.update', [$tenant, $user]) }}" class="inline">
                                @csrf @method('PATCH')
                                <select name="role" onchange="this.form.submit()"
                                        class="border border-gray-200 rounded px-2 py-1 text-xs">
                                    <option value="admin"  @selected($user->role === 'admin')>Admin</option>
                                    <option value="member" @selected($user->role === 'member')>Member</option>
                                </select>
                                <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                            </form>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-3" x-data="{ open: false }">
                                {{-- Reset password --}}
                                <button @click="open = !open" class="text-xs text-gray-500 hover:text-gray-700">Reset pw</button>
                                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="open=false">
                                    <form method="POST" action="{{ route('admin.tenant-users.reset-password', [$tenant, $user]) }}"
                                          class="bg-white rounded-xl shadow-xl p-6 w-80 space-y-3" @click.stop>
                                        @csrf @method('PATCH')
                                        <h3 class="text-sm font-semibold">Reset password for {{ $user->name }}</h3>
                                        <input type="text" name="password" placeholder="New password" required minlength="8"
                                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <input type="text" name="password_confirmation" placeholder="Confirm password"
                                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <div class="flex gap-2">
                                            <button class="flex-1 bg-indigo-600 text-white text-sm py-2 rounded-lg">Reset</button>
                                            <button type="button" @click="open=false" class="flex-1 border border-gray-300 text-sm py-2 rounded-lg">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                                {{-- Remove --}}
                                <form method="POST" action="{{ route('admin.tenant-users.destroy', [$tenant, $user]) }}"
                                      onsubmit="return confirm('Remove {{ $user->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
