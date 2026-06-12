<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\InboxMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboxMessage>
 */
class InboxMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                => User::factory(),
            'tenant_id'                => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'email_account_id'       => EmailAccount::factory(),
            'uid'                    => fake()->uuid(),
            'message_id'             => '<' . fake()->uuid() . '@example.com>',
            'in_reply_to'            => null,
            'from_email'             => fake()->safeEmail(),
            'from_name'              => fake()->name(),
            'subject'                => fake()->sentence(5),
            'body_text'              => fake()->paragraph(),
            'body_html'              => '<p>' . fake()->paragraph() . '</p>',
            'received_at'            => now()->subHours(rand(1, 48)),
            'is_read'                => false,
            'matched_contact_id'     => null,
            'matched_opportunity_id' => null,
            'matched_outbound_id'    => null,
            'review_status'          => 'pending',
            'sentiment'              => fake()->randomElement(['positive', 'neutral', 'negative', 'unknown']),
        ];
    }

    public function positive(): static
    {
        return $this->state(['sentiment' => 'positive']);
    }

    public function reviewed(): static
    {
        return $this->state(['review_status' => 'reviewed']);
    }
}
