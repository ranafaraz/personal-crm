@extends('layouts.app')
@section('title', 'API Integrations')
@section('page-title', 'API Integrations')

@section('content')
<div class="max-w-4xl space-y-6">

    {{-- One-time token reveal --}}
    @if(session('new_token'))
    <div class="bg-amber-50 border border-amber-300 rounded-xl p-5" x-data="{ copied: false }">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div class="flex-1">
                <p class="font-semibold text-amber-800 mb-1">Copy your API key now – it will not be shown again</p>
                <code class="block bg-white border border-amber-200 rounded-lg px-4 py-2 text-sm font-mono break-all text-slate-800 select-all" id="new-token">{{ session('new_token') }}</code>
                <button onclick="navigator.clipboard.writeText('{{ session('new_token') }}'); document.getElementById('copy-btn').innerText='Copied!';"
                    id="copy-btn"
                    class="mt-2 text-xs text-amber-700 underline hover:text-amber-900">
                    Copy to clipboard
                </button>
            </div>
        </div>
    </div>
    @endif

    @if(session('success') && !session('new_token'))
    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- OpenAPI / Setup info --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-800 space-y-2">
        <p class="font-semibold text-blue-900">Custom GPT Actions setup</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>Create an API client below (source type: <code>custom_gpt</code>).</li>
            <li>Copy the generated API key (shown once).</li>
            <li>In your Custom GPT → Actions → Add action → import schema from URL:</li>
        </ol>
        <code class="block bg-white border border-blue-200 rounded px-3 py-1 font-mono text-xs break-all">{{ url('/openapi/gpt-actions.json') }}</code>
        <p class="mt-1">Set <strong>Authentication</strong> → API Key → Custom header → <code>X-Api-Key</code> → paste your key.</p>
    </div>

    {{-- Existing clients --}}
    @forelse($clients as $client)
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div>
                <span class="font-semibold text-slate-800">{{ $client->name }}</span>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full
                    {{ $client->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                </span>
                <span class="ml-1 text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ $client->source_type }}</span>
            </div>
            <form method="POST" action="{{ route('integrations.clients.destroy', $client) }}"
                onsubmit="return confirm('Delete this client and all its tokens?')">
                @csrf @method('DELETE')
                <button class="text-xs text-red-500 hover:text-red-700">Delete client</button>
            </form>
        </div>

        <div class="px-5 py-3 text-xs text-slate-500 space-y-1">
            <p><strong>Scopes:</strong>
                @foreach($client->scopes ?? [] as $scope)
                    <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">{{ $scope }}</code>
                @endforeach
            </p>
            @if($client->last_used_at)
                <p><strong>Last used:</strong> {{ $client->last_used_at->diffForHumans() }}</p>
            @endif
            @if($client->expires_at)
                <p><strong>Expires:</strong> {{ $client->expires_at->toFormattedDayDateString() }}</p>
            @endif
        </div>

        {{-- Tokens list --}}
        <div class="border-t border-slate-100">
            @foreach($client->tokens as $token)
            <div class="flex items-center justify-between px-5 py-2 text-sm border-b border-slate-50 last:border-0">
                <div class="flex items-center gap-3">
                    <code class="text-xs text-slate-500">{{ $token->token_prefix }}…</code>
                    <span class="text-slate-700">{{ $token->name }}</span>
                    @unless($token->is_active)
                        <span class="text-xs text-slate-400">(revoked)</span>
                    @endunless
                    @if($token->last_used_at)
                        <span class="text-xs text-slate-400">· Used {{ $token->last_used_at->diffForHumans() }}</span>
                    @endif
                </div>
                @if($token->is_active)
                <form method="POST" action="{{ route('integrations.tokens.revoke', $token) }}">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">Revoke</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Add token to existing client --}}
        <details class="px-5 py-3 border-t border-slate-100 text-sm">
            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 text-xs">+ Add another token</summary>
            <form method="POST" action="{{ route('integrations.tokens.store', $client) }}" class="mt-3 flex gap-3">
                @csrf
                <input type="text" name="name" placeholder="Token label" required
                    class="flex-1 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Generate</button>
            </form>
        </details>
    </div>
    @empty
    <div class="text-center text-slate-400 py-10 border border-dashed border-slate-200 rounded-xl">
        No API clients yet. Create one below to get started.
    </div>
    @endforelse

    {{-- Create new client --}}
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Create New API Client</h3>

        <form method="POST" action="{{ route('integrations.clients.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Client Name</label>
                    <input type="text" name="name" required placeholder="e.g. My Custom GPT"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Source Type</label>
                    <select name="source_type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="custom_gpt">Custom GPT</option>
                        <option value="mcp">MCP</option>
                        <option value="n8n">n8n</option>
                        <option value="internal_agent">Internal Agent</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Scopes</label>
                @php
                $scopeGroups = [
                    'General'      => ['dashboard:read'],
                    'Contacts'     => ['contacts:read', 'contacts:write'],
                    'Opportunities'=> ['opportunities:read', 'opportunities:write'],
                    'Drafts'       => ['drafts:read', 'drafts:create'],
                    'Signatures'   => ['signatures:read', 'signatures:write'],
                    'Attachments'  => ['attachments:read', 'attachments:write'],
                    'Follow-ups'   => ['followups:read', 'followups:create'],
                    'Replies'      => ['replies:read'],
                    'Notes'        => ['notes:write'],
                ];
                $defaultScopes = ['dashboard:read', 'contacts:read', 'opportunities:read'];
                @endphp
                <div class="space-y-2">
                    @foreach($scopeGroups as $group => $scopes)
                    <div class="flex items-start gap-3">
                        <span class="w-28 shrink-0 text-xs font-medium text-slate-400 pt-0.5">{{ $group }}</span>
                        <div class="flex flex-wrap gap-x-5 gap-y-1">
                            @foreach($scopes as $scope)
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400"
                                    {{ in_array($scope, old('scopes', $defaultScopes)) ? 'checked' : '' }}>
                                <code class="text-xs text-slate-700">{{ $scope }}</code>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Expires At <span class="text-slate-400">(optional)</span></label>
                <input type="datetime-local" name="expires_at"
                    class="w-full sm:w-64 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>

            <button type="submit"
                class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                Create Client &amp; Generate Key
            </button>
        </form>
    </div>

    {{-- MCP setup info --}}
    <details class="bg-white border border-slate-200 rounded-xl">
        <summary class="px-5 py-4 font-semibold text-slate-800 cursor-pointer hover:text-indigo-700">MCP Adapter Setup</summary>
        <div class="px-5 pb-5 text-sm text-slate-600 space-y-3">
            <p>The MCP adapter lives in <code class="bg-slate-100 px-1 rounded">mcp/</code> in the project root. To run it:</p>
            <pre class="bg-slate-900 text-green-300 rounded-lg p-4 text-xs overflow-x-auto">cd mcp
cp .env.example .env
# Edit .env: set CRM_BASE_URL and CRM_API_KEY
npm install
npm run build
node dist/index.js</pre>
            <p>Add it to your MCP client config (e.g. Claude Desktop):</p>
            <pre class="bg-slate-900 text-green-300 rounded-lg p-4 text-xs overflow-x-auto">{
  "mcpServers": {
    "personal-crm": {
      "command": "node",
      "args": ["/path/to/personal-crm/mcp/dist/index.js"],
      "env": {
        "CRM_BASE_URL": "{{ rtrim(config('app.url'), '/') }}",
        "CRM_API_KEY": "pocrm_live_..."
      }
    }
  }
}</pre>
        </div>
    </details>

</div>
@endsection
