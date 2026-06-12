<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name'      => $name,
            'slug'      => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'email'     => fake()->unique()->companyEmail(),
            // Most permissive tier by default so feature tests exercising
            // unrelated functionality don't trip plan limits.
            'plan'      => 'enterprise',
            'status'    => 'active',
            'max_users' => 5,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['plan' => 'free', 'max_users' => 1]);
    }

    public function trial(int $daysLeft = 14): static
    {
        return $this->state(fn () => [
            'plan'          => 'free',
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays($daysLeft),
        ]);
    }
}
