<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// 1. Tenant
$tenant = Tenant::firstOrCreate(
    ['slug' => 'default'],
    ['name' => 'Personal CRM', 'plan' => 'pro', 'status' => 'active']
);
echo "Tenant ID: {$tenant->id}\n";

// 2. Admin user
$user = User::where('email', 'ranafarazahmed@gmail.com')->first();
if ($user) {
    $user->update([
        'password'  => Hash::make('dexdevs007'),
        'role'      => 'super_admin',
        'tenant_id' => $tenant->id,
    ]);
    echo "User updated (ID {$user->id})\n";
} else {
    $user = User::create([
        'name'      => 'Rana Faraz',
        'email'     => 'ranafarazahmed@gmail.com',
        'password'  => Hash::make('dexdevs007'),
        'role'      => 'super_admin',
        'tenant_id' => $tenant->id,
    ]);
    echo "User created (ID {$user->id})\n";
}

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
        'smtp_password'     => 'vpwjarzjwnhrsphh',
        'imap_host'         => 'imap.gmail.com',
        'imap_port'         => 993,
        'imap_encryption'   => 'ssl',
        'imap_username'     => 'ranafarazahmed@gmail.com',
        'imap_password'     => 'vpwjarzjwnhrsphh',
        'daily_limit'       => 500,
        'hourly_limit'      => 50,
        'min_delay_seconds' => 10,
        'is_active'         => true,
        'is_default'        => true,
        'notes'             => 'Personal Gmail via App Password',
    ]);
    echo "Email account created (ID {$account->id})\n";
} else {
    echo "Email account already exists (ID {$account->id})\n";
}

echo "Done.\n";
