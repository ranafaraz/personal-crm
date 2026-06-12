@extends('layouts.app')
@section('title', 'Billing')
@section('page-title', 'Billing & Plan')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Current plan --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Current plan</h2>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $plans[$effectivePlan]['label'] }}</p>
                @if ($tenant->isTrial() && $tenant->trial_ends_at)
                    <p class="text-sm text-amber-600 mt-1">
                        Trial — Pro features until {{ $tenant->trial_ends_at->format('M j, Y') }}
                    </p>
                @endif
                @if ($onGracePeriod)
                    <p class="text-sm text-amber-600 mt-1">
                        Cancels {{ $subscription->ends_at?->format('M j, Y') }} — you keep access until then.
                    </p>
                @endif
            </div>
            <div class="flex gap-2">
                @if ($subscription && $subscription->valid())
                    @if ($onGracePeriod)
                        <form method="POST" action="{{ route('billing.resume') }}">
                            @csrf
                            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                Resume subscription
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('billing.cancel') }}"
                              onsubmit="return confirm('Cancel your subscription? You keep access until the end of the billing period.');">
                            @csrf
                            <button class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Cancel subscription
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Usage --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Usage</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach ($usage as $key => $info)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs text-gray-500">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ number_format($info['used']) }}
                        <span class="text-sm font-normal text-gray-400">
                            / {{ $info['limit'] === null ? 'Unlimited' : number_format($info['limit']) }}
                        </span>
                    </p>
                    @if ($info['limit'] !== null)
                        <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $info['used'] >= $info['limit'] ? 'bg-red-500' : 'bg-indigo-500' }}"
                                 style="width: {{ min(100, $info['limit'] > 0 ? ($info['used'] / $info['limit']) * 100 : 100) }}%"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Plans --}}
    <div class="grid md:grid-cols-3 gap-4">
        @foreach ($plans as $key => $plan)
            <div class="bg-white rounded-xl border {{ $key === $effectivePlan ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200' }} p-5 flex flex-col">
                <h3 class="text-sm font-semibold text-gray-700">{{ $plan['label'] }}</h3>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    ${{ $plan['monthly_price'] }}<span class="text-sm font-normal text-gray-400">/mo{{ $key === 'enterprise' ? ' per seat' : '' }}</span>
                </p>
                <ul class="text-sm text-gray-600 mt-3 space-y-1 flex-1">
                    <li>{{ $plan['limits']['contacts'] === null ? 'Unlimited' : number_format($plan['limits']['contacts']) }} contacts</li>
                    <li>{{ $plan['limits']['email_accounts'] }} email account{{ $plan['limits']['email_accounts'] === 1 ? '' : 's' }}</li>
                    <li>{{ number_format($plan['limits']['emails_per_day']) }} emails/day</li>
                    <li>{{ $plan['limits']['social_accounts'] }} social account{{ $plan['limits']['social_accounts'] === 1 ? '' : 's' }}</li>
                    <li>{{ $plan['features']['follow_up_automation'] ? 'Automated follow-ups' : 'Manual follow-ups' }}</li>
                    <li>{{ $plan['features']['api_access'] ? 'API, GPT & MCP access' : 'No API access' }}</li>
                </ul>
                @if ($key !== 'free' && $key !== $tenant->plan)
                    @if ($paddleReady)
                        <a href="{{ route('billing.checkout', ['plan' => $key, 'period' => 'monthly']) }}"
                           class="mt-4 block text-center px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            Upgrade to {{ $plan['label'] }}
                        </a>
                    @else
                        <p class="mt-4 text-xs text-gray-400 text-center">Billing not configured</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-400">
        Transparent pricing — no credits, no hidden add-ons. You can export all of your data at any time from Settings.
    </p>
</div>
@endsection
