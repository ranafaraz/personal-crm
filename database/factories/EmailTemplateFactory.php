<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'tenant_id'    => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'name'       => fake()->sentence(3),
            'type'       => fake()->randomElement(['initial_outreach', 'follow_up', 'networking', 'other']),
            'subject'    => fake()->sentence(5),
            'body'       => '<p>' . fake()->paragraph() . '</p>',
            'variables'  => ['first_name', 'your_name'],
            'is_active'  => true,
            'times_used' => 0,
        ];
    }
}
