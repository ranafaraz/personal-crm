<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Models\User;
use Illuminate\Console\Command;

class CreateApiClientCommand extends Command
{
    protected $signature = 'crm:api-client:create
        {email : User email}
        {name  : Client name}
        {--source=custom_gpt : Source type}
        {--scopes= : Comma-separated scopes (default: all read+write)}';

    protected $description = 'Create an API client and token for a user (prints raw token once)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("User not found: {$this->argument('email')}");
            return self::FAILURE;
        }

        $scopeArg = $this->option('scopes');
        $scopes   = $scopeArg
            ? explode(',', $scopeArg)
            : ['dashboard:read','opportunities:read','opportunities:write','contacts:read','contacts:write','drafts:read','drafts:create','followups:read','followups:create','replies:read','notes:write'];

        $client = ApiClient::create([
            'user_id'     => $user->id,
            'name'        => $this->argument('name'),
            'source_type' => $this->option('source'),
            'scopes'      => $scopes,
            'is_active'   => true,
        ]);

        ['raw' => $raw, 'hash' => $hash, 'prefix' => $prefix] = ApiClientToken::generateRaw();

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $user->id,
            'name'          => 'Default Token',
            'token_hash'    => $hash,
            'token_prefix'  => $prefix,
            'is_active'     => true,
        ]);

        $this->info("✓ API client created: {$client->name} (ID: {$client->id})");
        $this->line('');
        $this->warn('Copy this key now – it will NOT be shown again:');
        $this->line($raw);

        return self::SUCCESS;
    }
}
