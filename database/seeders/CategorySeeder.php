<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * System/default categories are shared across all users (user_id = NULL).
     * Seeding is idempotent: re-running never creates duplicates.
     *
     * @var list<array{name: string, category_type: string, icon: string|null}>
     */
    private const SYSTEM_CATEGORIES = [
        ['name' => 'Food', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Transport', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Shopping', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Loans', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Subscriptions', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Insurance', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Investments', 'category_type' => 'INVESTMENT', 'icon' => null],
        ['name' => 'Income', 'category_type' => 'INCOME', 'icon' => null],
    ];

    /**
     * Seed the application's system/default categories.
     */
    public function run(): void
    {
        foreach (self::SYSTEM_CATEGORIES as $category) {
            Category::query()->firstOrCreate(
                [
                    'user_id' => null,
                    'name' => $category['name'],
                ],
                [
                    'category_type' => $category['category_type'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
