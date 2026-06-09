@extends('layouts.app')
@section('title', 'Social Connections')

@section('content')
<div class="p-6 space-y-6 max-w-6xl" data-connections-page>

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Connections</h1>
            <p class="text-sm text-slate-500 mt-1">Connect channels once, then use them as publish targets in Content.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('social-studio.oauth-apps.index') }}"
               class="inline-flex items-center text-sm text-slate-700 hover:text-slate-900 border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 px-3 py-2 rounded-lg transition">
                LinkedIn Apps
            </a>
            <button type="button" data-open-wp-modal
                    class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Add WordPress Site
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    @php
        $linkedInApps = $oauthApps->where('provider_key', 'linkedin');
        $wordpressAccounts = $accounts->filter(fn ($account) => $account->provider?->key === 'wordpress');
        $otherProviders = $providers->whereNotIn('key', ['linkedin', 'wordpress']);
        $accountsByProvider = $accounts->groupBy(fn ($account) => $account->provider?->key ?? 'unknown');
    @endphp

    <div class="grid xl:grid-cols-2 gap-5">
        <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">LinkedIn</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Profiles connected through your LinkedIn developer apps.</p>
                </div>
                @if($linkedInApps->isNotEmpty())
                    <a href="{{ route('social-studio.connections.connect', ['app_id' => $linkedInApps->first()->id]) }}"
                       class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg transition">Add Profile</a>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($linkedInApps as $app)
                    <div class="px-5 py-4 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $app->label }}</p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $app->client_id }}</p>
                            </div>
                            @if($app->is_default)
                                <span class="text-[11px] font-semibold bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5">Default App</span>
                            @endif
                        </div>

                        @forelse($app->accounts as $account)
                            @include('social-studio.partials.connection-row', ['account' => $account])
                        @empty
                            <div class="border border-dashed border-slate-300 rounded-lg p-4 text-sm text-slate-500">
                                No profiles connected for this app.
                            </div>
                        @endforelse
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-slate-500">No LinkedIn app configured yet.</p>
                        <a href="{{ route('social-studio.oauth-apps.create') }}"
                           class="mt-3 inline-flex bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Add LinkedIn App
                        </a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">WordPress</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Sites connected with WordPress application passwords.</p>
                </div>
                <button type="button" data-open-wp-modal
                        class="text-xs bg-slate-900 hover:bg-slate-800 text-white font-medium px-3 py-1.5 rounded-lg transition">
                    Add Site
                </button>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($wordpressAccounts as $account)
                    <div class="px-5 py-4">
                        @include('social-studio.partials.connection-row', ['account' => $account])
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-slate-500">No WordPress sites connected yet.</p>
                        <button type="button" data-open-wp-modal class="mt-3 inline-flex text-sm text-indigo-600 hover:underline">Connect a site</button>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Other Channels</h2>
                <p class="text-xs text-slate-400 mt-0.5">Connect multiple profiles, pages, CMS sites, or channels for account tracking and publishing activity.</p>
            </div>
            <button type="button" data-open-manual-modal
                    class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-3 py-1.5 rounded-lg transition">
                Add Channel
            </button>
        </div>
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3 p-5">
            @foreach($otherProviders as $provider)
                @php $providerAccounts = $accountsByProvider->get($provider->key, collect()); @endphp
                <div class="border border-slate-200 rounded-lg p-3 space-y-3 {{ $provider->status === 'enabled' ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $provider->name }}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ $providerAccounts->where('status', 'connected')->count() }} connected · {{ str_replace('_', ' ', ucfirst($provider->status)) }}
                            </p>
                        </div>
                        @if($provider->status === 'enabled')
                            <button type="button"
                                    data-open-manual-modal
                                    data-provider-key="{{ $provider->key }}"
                                    class="text-xs text-indigo-600 hover:underline flex-shrink-0">
                                Connect
                            </button>
                        @endif
                    </div>

                    @foreach($providerAccounts as $account)
                        <div class="border-t border-slate-100 pt-3">
                            @include('social-studio.partials.connection-row', ['account' => $account])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <div data-manual-modal class="hidden fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" data-close-manual-modal></div>
        <div class="relative mx-auto mt-10 w-full max-w-2xl px-4">
            <form method="POST" action="{{ route('social-studio.connections.manual.store') }}"
                  class="bg-white rounded-lg shadow-xl border border-slate-200">
                @csrf
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Connect Channel</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Use this for Facebook pages, Instagram business accounts, X profiles, Drupal/Joomla sites, and other channels.</p>
                    </div>
                    <button type="button" data-close-manual-modal class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="manual_provider_key" class="block text-xs font-medium text-slate-700 mb-1">Provider <span class="text-red-500">*</span></label>
                            <select id="manual_provider_key" name="provider_key" required
                                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                <option value="">Choose provider</option>
                                @foreach($otherProviders->where('status', 'enabled') as $provider)
                                    <option value="{{ $provider->key }}" data-fields="{{ implode(',', $provider->capabilities_json['manual_fields'] ?? []) }}" {{ old('provider_key') === $provider->key ? 'selected' : '' }}>
                                        {{ $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="manual_display_name" class="block text-xs font-medium text-slate-700 mb-1">Display Name <span class="text-red-500">*</span></label>
                            <input type="text" id="manual_display_name" name="display_name" value="{{ old('display_name') }}" required placeholder="Brand page, site, or channel name"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="manual_account_identifier" class="block text-xs font-medium text-slate-700 mb-1">Account / Page / Channel ID</label>
                            <input type="text" id="manual_account_identifier" name="account_identifier" value="{{ old('account_identifier') }}" placeholder="Page ID, handle, channel ID, or site key"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                        <div>
                            <label for="manual_profile_url" class="block text-xs font-medium text-slate-700 mb-1">Profile URL</label>
                            <input type="url" id="manual_profile_url" name="profile_url" value="{{ old('profile_url') }}" placeholder="https://..."
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="manual_base_url" class="block text-xs font-medium text-slate-700 mb-1">Site URL</label>
                            <input type="url" id="manual_base_url" name="base_url" value="{{ old('base_url') }}" placeholder="https://site.example.com"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                        <div>
                            <label for="manual_api_base" class="block text-xs font-medium text-slate-700 mb-1">API Base URL</label>
                            <input type="url" id="manual_api_base" name="api_base" value="{{ old('api_base') }}" placeholder="https://site.example.com/api"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="manual_username" class="block text-xs font-medium text-slate-700 mb-1">Username / Handle</label>
                            <input type="text" id="manual_username" name="username" value="{{ old('username') }}"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                        <div>
                            <label for="manual_access_token" class="block text-xs font-medium text-slate-700 mb-1">Access Token / App Password</label>
                            <input type="password" id="manual_access_token" name="access_token"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>

                    <div>
                        <label for="manual_notes" class="block text-xs font-medium text-slate-700 mb-1">Notes</label>
                        <textarea id="manual_notes" name="notes" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100 bg-slate-50">
                    <button type="button" data-close-manual-modal class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                        Connect Channel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div data-wp-modal class="hidden fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" data-close-wp-modal></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg px-4">
            <form method="POST" action="{{ route('social-studio.connections.wordpress.store') }}"
                  class="bg-white rounded-lg shadow-xl border border-slate-200">
                @csrf
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Connect WordPress Site</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Use a WordPress application password from the site user profile.</p>
                    </div>
                    <button type="button" data-close-wp-modal class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label for="wp_site_url" class="block text-xs font-medium text-slate-700 mb-1">Site URL <span class="text-red-500">*</span></label>
                        <input type="url" id="wp_site_url" name="site_url" value="{{ old('site_url') }}" required placeholder="https://blog.example.com"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label for="wp_label" class="block text-xs font-medium text-slate-700 mb-1">Display Name</label>
                        <input type="text" id="wp_label" name="label" value="{{ old('label') }}" placeholder="Company Blog"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="wp_username" class="block text-xs font-medium text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="wp_username" name="username" value="{{ old('username') }}" required
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                        <div>
                            <label for="wp_application_password" class="block text-xs font-medium text-slate-700 mb-1">Application Password <span class="text-red-500">*</span></label>
                            <input type="password" id="wp_application_password" name="application_password" required
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100 bg-slate-50">
                    <button type="button" data-close-wp-modal class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                        Connect Site
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-wp-modal]');
    const openButtons = document.querySelectorAll('[data-open-wp-modal]');
    const closeButtons = document.querySelectorAll('[data-close-wp-modal]');
    const manualModal = document.querySelector('[data-manual-modal]');
    const manualProvider = document.getElementById('manual_provider_key');
    const manualOpenButtons = document.querySelectorAll('[data-open-manual-modal]');
    const manualCloseButtons = document.querySelectorAll('[data-close-manual-modal]');

    function openModal() {
        modal?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function openManualModal(providerKey) {
        if (providerKey && manualProvider) {
            manualProvider.value = providerKey;
        }
        manualModal?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeManualModal() {
        manualModal?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    openButtons.forEach(button => button.addEventListener('click', openModal));
    closeButtons.forEach(button => button.addEventListener('click', closeModal));
    manualOpenButtons.forEach(button => button.addEventListener('click', function () {
        openManualModal(button.dataset.providerKey || '');
    }));
    manualCloseButtons.forEach(button => button.addEventListener('click', closeManualModal));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeModal();
            closeManualModal();
        }
    });

    @if($errors->has('site_url') || $errors->has('username') || $errors->has('application_password'))
        openModal();
    @endif

    @if($errors->has('provider_key') || $errors->has('display_name') || $errors->has('access_token') || $errors->has('base_url') || $errors->has('api_base') || $errors->has('profile_url'))
        openManualModal(@json(old('provider_key')));
    @endif
});
</script>
@endpush
@endsection
