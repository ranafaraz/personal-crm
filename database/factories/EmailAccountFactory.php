<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'user_id'           => User::factory(),
            'tenant_id'           => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'name'              => fake()->company() . ' Outreach',
            'email'             => $email,
            'from_name'         => fake()->name(),
            'smtp_host'         => 'smtp.example.com',
            'smtp_port'         => 587,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => $email,
            'smtp_password'     => 'smtp-test-password',
            'imap_host'         => 'imap.example.com',
            'imap_port'         => 993,
            'imap_encryption'   => 'ssl',
            'imap_username'     => $email,
            'imap_password'     => 'imap-test-password',
            'daily_limit'       => 50,
            'hourly_limit'      => 10,
            'min_delay_seconds' => 30,
            'emails_sent_today' => 0,
            'last_reset_at'     => now(),
            'sync_status'       => 'idle',
            'is_active'         => true,
            'is_default'        => false,
            'notes'             => null,
        ];
    }

    /**
     * State for an inactive account.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * State for the default account.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * State for an account that has reached its daily limit.
     */
    public function atDailyLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_limit'       => 50,
            'emails_sent_today' => 50,
        ]);
    }
}
