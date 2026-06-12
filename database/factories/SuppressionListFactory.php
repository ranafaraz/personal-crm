<?php

namespace Database\Factories;

use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuppressionList>
 */
class SuppressionListFactory extends Factory
{
    protected $model = SuppressionList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attrs) => \App\Models\User::find($attrs['user_id'])?->tenant_id,
            'email'   => fake()->unique()->safeEmail(),
            'reason'  => fake()->randomElement(['unsubscribe', 'bounce', 'complaint', 'manual']),
            'notes'   => fake()->optional(0.4)->sentence(),
        ];
    }
}
