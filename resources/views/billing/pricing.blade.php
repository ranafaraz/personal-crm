<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pricing — {{ config('app.name', 'Personal Outreach CRM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="max-w-5xl mx-auto px-6 py-16">
        <div class="text-center">
            <h1 class="text-3xl font-bold">Simple, transparent pricing</h1>
            <p class="text-gray-500 mt-2">No credits. No hidden add-ons. Export your data anytime.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mt-12">
            @foreach (config('plans.plans') as $key => $plan)
                <div class="bg-white rounded-2xl border {{ $key === 'pro' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200' }} p-6 flex flex-col">
                    <h2 class="text-sm font-semibold text-gray-700">{{ $plan['label'] }}</h2>
                    <p class="text-3xl font-bold mt-2">
                        ${{ $plan['monthly_price'] }}<span class="text-sm font-normal text-gray-400">/mo{{ $key === 'enterprise' ? ' per seat' : '' }}</span>
                    </p>
                    <ul class="text-sm text-gray-600 mt-4 space-y-2 flex-1">
                        <li>{{ $plan['limits']['contacts'] === null ? 'Unlimited' : number_format($plan['limits']['contacts']) }} contacts</li>
                        <li>{{ $plan['limits']['users'] }} {{ $plan['limits']['users'] === 1 ? 'seat' : 'seats' }}</li>
                        <li>{{ $plan['limits']['email_accounts'] }} email account{{ $plan['limits']['email_accounts'] === 1 ? '' : 's' }}</li>
                        <li>{{ number_format($plan['limits']['emails_per_day']) }} emails/day</li>
                        <li>{{ $plan['limits']['social_accounts'] }} social account{{ $plan['limits']['social_accounts'] === 1 ? '' : 's' }}</li>
                        <li>{{ $plan['features']['follow_up_automation'] ? 'Automated follow-ups' : 'Manual follow-ups' }}</li>
                        <li>{{ $plan['features']['api_access'] ? 'API, GPT & MCP access' : '—' }}</li>
                        @if ($plan['features']['approval_workflows'])
                            <li>Team approval workflows & audit logs</li>
                        @endif
                    </ul>
                    <a href="{{ route('register') }}"
                       class="mt-6 block text-center px-4 py-2.5 text-sm font-medium rounded-lg {{ $key === 'pro' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        {{ $key === 'free' ? 'Start free' : 'Start 14-day free trial' }}
                    </a>
                </div>
            @endforeach
        </div>

        <p class="text-center text-xs text-gray-400 mt-10">
            All plans include full data export. Every new account starts with a 14-day Pro trial — no card required.
        </p>
    </div>
</body>
</html>
