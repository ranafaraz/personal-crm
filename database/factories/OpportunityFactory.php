<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
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
            'title'            => fake()->sentence(4),
            'type'             => fake()->randomElement(['job', 'scholarship', 'research', 'grant', 'networking']),
            'organization'     => fake()->company(),
            'description'      => fake()->optional(0.7)->paragraph(),
            'url'              => fake()->optional(0.5)->url(),
            'status'           => 'active',
            'priority'         => fake()->randomElement(['low', 'medium', 'high']),
            'deadline'         => fake()->optional(0.5)->dateTimeBetween('now', '+6 months')?->format('Y-m-d'),
            'notes'            => fake()->optional(0.4)->sentence(),
            'last_activity_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * State for a job opportunity.
     */
    public function job(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'job',
        ]);
    }

    /**
     * State for a scholarship opportunity.
     */
    public function scholarship(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'scholarship',
        ]);
    }

    /**
     * State for a waiting-reply opportunity.
     */
    public function waitingReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'waiting_reply',
        ]);
    }

    /**
     * State for a draft opportunity.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}
