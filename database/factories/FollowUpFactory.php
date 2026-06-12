<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUp>
 */
class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'tenant_id'          => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'opportunity_id'   => Opportunity::factory(),
            'contact_id'       => null,
            'email_account_id' => EmailAccount::factory(),
            'email_template_id'=> null,
            'email_message_id' => null,
            'follow_up_number' => 1,
            'due_at'           => now()->addDays(rand(1, 7)),
            'sent_at'          => null,
            'status'           => 'pending',
            'cancel_reason'    => null,
            'subject'          => null,
            'body'             => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(['due_at' => now()->subDays(rand(1, 7))]);
    }

    public function dueToday(): static
    {
        return $this->state(['due_at' => now()->startOfDay()]);
    }
}
