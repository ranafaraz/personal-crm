@extends('layouts.app')
@section('title', 'Edit — ' . $tenant->name)
@section('page-title', 'Edit Tenant: ' . $tenant->name)

@section('content')
<div class="max-w-2xl">
<form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Organization Name *</label>
                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Contact Email</label>
                <input type="email" name="email" value="{{ old('email', $tenant->email) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Max Users</label>
                <input type="number" name="max_users" value="{{ old('max_users', $tenant->max_users) }}" min="1"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                <select name="plan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach (['free','pro','enterprise'] as $p)
                        <option value="{{ $p }}" @selected(old('plan', $tenant->plan) === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach (['active','trial','suspended','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $tenant->status) === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Trial Ends At</label>
                <input type="date" name="trial_ends_at"
                       value="{{ old('trial_ends_at', $tenant->trial_ends_at?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes', $tenant->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Save Changes
        </button>
        <a href="{{ route('admin.tenants.show', $tenant) }}" class="px-5 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>
</div>
@endsection
