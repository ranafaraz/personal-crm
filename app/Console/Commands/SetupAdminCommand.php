<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupAdminCommand extends Command
{
    protected $signature   = 'crm:setup-admin';
    protected $description = 'Create the default tenant, admin user, and Gmail email account';

    public function handle(): int
    {
        // 1. Tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Personal CRM', 'plan' => 'pro', 'status' => 'active']
        );
        $this->info("Tenant: {$tenant->name} (ID {$tenant->id})");

        // 2. Admin user
        $user = User::updateOrCreate(
            ['email' => 'ranafarazahmed@gmail.com'],
            [
                'name'      => 'Rana Faraz',
                'password'  => Hash::make('dexdevs007'),
                'role'      => 'super_admin',
                'tenant_id' => $tenant->id,
            ]
        );
        $this->info("User: {$user->email} (ID {$user->id}) [" . ($user->wasRecentlyCreated ? 'created' : 'updated') . "]");

        // 3. Gmail email account
        $account = EmailAccount::where('user_id', $user->id)
            ->where('email', 'ranafarazahmed@gmail.com')
            ->first();

        if (!$account) {
            $account = EmailAccount::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $user->id,
                'name'              => 'Personal Gmail',
                'email'             => 'ranafarazahmed@gmail.com',
                'from_name'         => 'Rana Faraz',
                'smtp_host'         => 'smtp.gmail.com',
                'smtp_port'         => 587,
                'smtp_encryption'   => 'tls',
                'smtp_username'     => 'ranafarazahmed@gmail.com',
                'smtp_password'     => env('GMAIL_APP_PASSWORD', 'vpwjarzjwnhrsphh'),
                'imap_host'         => 'imap.gmail.com',
                'imap_port'         => 993,
                'imap_encryption'   => 'ssl',
                'imap_username'     => 'ranafarazahmed@gmail.com',
                'imap_password'     => env('GMAIL_APP_PASSWORD', 'vpwjarzjwnhrsphh'),
                'daily_limit'       => 500,
                'hourly_limit'      => 50,
                'min_delay_seconds' => 10,
                'is_active'         => true,
                'is_default'        => true,
                'notes'             => 'Personal Gmail via App Password',
            ]);
            $this->info("Email account created (ID {$account->id})");
        } else {
            $this->info("Email account already exists (ID {$account->id})");
        }

        // 4. Also create dexdevs007@gmail.com as a secondary demo account
        $demo = EmailAccount::where('user_id', $user->id)
            ->where('email', 'dexdevs007@gmail.com')
            ->first();

        if (!$demo) {
            $demo = EmailAccount::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $user->id,
                'name'              => 'DEXDevs Gmail',
                'email'             => 'dexdevs007@gmail.com',
                'from_name'         => 'DEXDevs',
                'smtp_host'         => 'smtp.gmail.com',
                'smtp_port'         => 587,
                'smtp_encryption'   => 'tls',
                'smtp_username'     => 'dexdevs007@gmail.com',
                'smtp_password'     => 'placeholder_update_in_app',
                'imap_host'         => 'imap.gmail.com',
                'imap_port'         => 993,
                'imap_encryption'   => 'ssl',
                'imap_username'     => 'dexdevs007@gmail.com',
                'imap_password'     => 'placeholder_update_in_app',
                'daily_limit'       => 200,
                'hourly_limit'      => 30,
                'min_delay_seconds' => 15,
                'is_active'         => true,
                'is_default'        => false,
                'notes'             => 'DEXDevs demo Gmail — update password in Email Accounts settings',
            ]);
            $this->info("Demo email account created (ID {$demo->id}) — update its app password in settings");
        } else {
            $this->info("Demo email account already exists (ID {$demo->id})");
        }

        $this->newLine();
        $this->info('Setup complete. Login at http://localhost:8080/login');
        $this->info('  Email:    ranafarazahmed@gmail.com');
        $this->info('  Password: dexdevs007');

        return self::SUCCESS;
    }
}
