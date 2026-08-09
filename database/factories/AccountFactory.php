<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'institution' => fake()->company(),
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => now()->subYear()->toDateString(),
            'status' => 'ACTIVE',
        ];
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'LIABILITY',
            'subtype' => 'CREDIT_CARD',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CLOSED',
        ]);
    }
}
