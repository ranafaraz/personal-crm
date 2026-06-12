<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'first_name'       => fake()->firstName(),
            'last_name'        => fake()->lastName(),
            'email'            => fake()->unique()->safeEmail(),
            'phone'            => fake()->optional(0.6)->phoneNumber(),
            'company'          => fake()->optional(0.8)->company(),
            'job_title'        => fake()->optional(0.7)->jobTitle(),
            'linkedin_url'     => null,
            'website'          => null,
            'country'          => fake()->optional(0.7)->country(),
            'city'             => fake()->optional(0.7)->city(),
            'notes'            => fake()->optional(0.4)->sentence(),
            'status'           => 'active',
            'source'           => fake()->randomElement(['manual', 'linkedin', 'referral', 'conference', 'website']),
            'last_contacted_at' => fake()->optional(0.6)->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * State for a suppressed contact.
     */
    public function suppressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suppressed',
        ]);
    }

    /**
     * State for an inactive contact.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
