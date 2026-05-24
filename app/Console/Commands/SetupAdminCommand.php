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

        // 4. Remove the legacy dexdevs007@gmail.com email account if a previous
        // run of this command created it — the user has switched to dexdevs@gmail.com
        // and that's now handled by crm:seed-user.
        $legacy = EmailAccount::where('user_id', $user->id)
            ->where('email', 'dexdevs007@gmail.com')
            ->first();
        if ($legacy) {
            $legacy->forceDelete();
            $this->info("Removed legacy email account dexdevs007@gmail.com");
        }

        $this->newLine();
        $this->info('Setup complete. Login at http://localhost:8080/login');
        $this->info('  Email:    ranafarazahmed@gmail.com');
        $this->info('  Password: dexdevs007');
        $this->newLine();
        $this->info('Next: run  php artisan crm:seed-user ranafarazahmed@gmail.com  to seed templates + email accounts.');

        return self::SUCCESS;
    }
}
