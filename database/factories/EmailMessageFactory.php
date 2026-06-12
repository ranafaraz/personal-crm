<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'tenant_id'          => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'email_account_id' => EmailAccount::factory(),
            'contact_id'       => null,
            'opportunity_id'   => null,
            'template_id'      => null,
            'message_id'       => '<' . fake()->uuid() . '@example.com>',
            'subject'          => fake()->sentence(6),
            'body'             => '<p>' . fake()->paragraph() . '</p>',
            'to_email'         => fake()->safeEmail(),
            'to_name'          => fake()->name(),
            'cc'               => null,
            'bcc'              => null,
            'status'           => 'sent',
            'direction'        => 'outbound',
            'scheduled_at'     => null,
            'sent_at'          => now()->subHour(),
            'failed_at'        => null,
            'failure_reason'   => null,
            'is_follow_up'     => false,
            'follow_up_number' => 0,
            'parent_message_id'=> null,
            'opened_at'        => null,
        ];
    }

    /**
     * State for a scheduled email.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'scheduled',
            'scheduled_at' => now()->addHour(),
            'sent_at'      => null,
        ]);
    }

    /**
     * State for a scheduled email that is now due.
     */
    public function scheduledDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'scheduled',
            'scheduled_at' => now()->subMinutes(5),
            'sent_at'      => null,
        ]);
    }

    /**
     * State for a failed email.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'failed',
            'sent_at'        => null,
            'failed_at'      => now(),
            'failure_reason' => 'SMTP connection refused.',
        ]);
    }
}
