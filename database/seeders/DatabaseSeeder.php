<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only system-level defaults are seeded here. Mock accounts, transactions,
     * payments, statements, and balances must never be seeded into production.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
    }
}
