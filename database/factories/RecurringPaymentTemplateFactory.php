<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPaymentTemplate>
 */
class RecurringPaymentTemplateFactory extends Factory
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
            'name' => fake()->words(3, true),
            'amount' => '1000.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY_OF_MONTH:5',
            'category_id' => Category::factory(),
            'is_mandatory' => true,
            'starts_on' => now()->subYear()->toDateString(),
            'status' => 'ACTIVE',
        ];
    }
}
