@extends('layouts.app')
@section('title', 'Checkout')
@section('page-title', 'Checkout')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center space-y-4">
        <h1 class="text-lg font-bold text-gray-900">
            Subscribe to {{ $plan['label'] }} ({{ $period }})
        </h1>
        <p class="text-sm text-gray-500">
            ${{ $plan['monthly_price'] }}/month{{ $period === 'annual' ? ', billed annually' : '' }} — payments are processed securely by Paddle.
        </p>

        <x-paddle-button :checkout="$checkout"
                         class="px-5 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            Complete checkout
        </x-paddle-button>

        <p class="text-xs text-gray-400">
            <a href="{{ route('billing.index') }}" class="underline">Back to billing</a>
        </p>
    </div>
</div>
@endsection

@push('scripts')
    @paddleJS
@endpush
