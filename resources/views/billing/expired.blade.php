@extends('layouts.app')
@section('title', 'Trial Ended')
@section('page-title', 'Your trial has ended')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <h1 class="text-xl font-bold text-gray-900">Your 14-day Pro trial has ended</h1>
        <p class="text-sm text-gray-500 mt-2">
            Your data is safe. Upgrade to keep Pro features, or continue on the Free plan.
        </p>

        <div class="grid md:grid-cols-2 gap-4 mt-8 text-left">
            @foreach (['pro', 'enterprise'] as $key)
                @php($plan = $plans[$key])
                <div class="border border-gray-200 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $plan['label'] }}</h3>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        ${{ $plan['monthly_price'] }}<span class="text-sm font-normal text-gray-400">/mo{{ $key === 'enterprise' ? ' per seat' : '' }}</span>
                    </p>
                    @if ($paddleReady)
                        <a href="{{ route('billing.checkout', ['plan' => $key, 'period' => 'monthly']) }}"
                           class="mt-4 block text-center px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            Upgrade to {{ $plan['label'] }}
                        </a>
                    @else
                        <p class="mt-4 text-xs text-gray-400 text-center">Billing not configured — contact support</p>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('billing.continue-free') }}" class="mt-6">
            @csrf
            <button class="text-sm text-gray-500 underline hover:text-gray-700">
                Continue on the Free plan ({{ $plans['free']['limits']['contacts'] }} contacts, 1 email account)
            </button>
        </form>
    </div>
</div>
@endsection
