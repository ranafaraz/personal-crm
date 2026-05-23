@extends('layouts.app')
@section('title', 'New Tenant')
@section('page-title', 'Create Tenant')

@section('content')
<div class="max-w-2xl">
<form method="POST" action="{{ route('admin.tenants.store') }}" class="space-y-6">
    @csrf

    {{-- Tenant info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2">Tenant Details</h2>

        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Organization Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Contact Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Max Users</label>
                <input type="number" name="max_users" value="{{ old('max_users', 5) }}" min="1" max="1000"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                <select name="plan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach (['free','pro','enterprise'] as $p)
                        <option value="{{ $p }}" @selected(old('plan','free') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach (['trial','active','suspended','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(old('status','trial') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Trial Ends At</label>
                <input type="date" name="trial_ends_at" value="{{ old('trial_ends_at', now()->addDays(14)->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes (internal)</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>

    {{-- Admin user --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2">Initial Admin User</h2>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Full Name *</label>
                <input type="text" name="admin_name" value="{{ old('admin_name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                <input type="email" name="admin_email" value="{{ old('admin_email') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Password *</label>
                <input type="text" name="admin_password" placeholder="Minimum 8 characters"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">Share this with the tenant admin. They can change it after first login.</p>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Create Tenant
        </button>
        <a href="{{ route('admin.tenants.index') }}"
           class="px-5 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>
</div>
@endsection
