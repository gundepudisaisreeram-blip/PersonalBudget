<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_system_categories_with_null_user_id(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertTrue(Category::whereNull('user_id')->where('name', 'Food')->exists());
        $this->assertTrue(Category::whereNull('user_id')->where('name', 'Income')->exists());
        $this->assertSame(8, Category::whereNull('user_id')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(8, Category::whereNull('user_id')->count());
    }

    public function test_system_category_is_usable_and_owned_by_every_user(): void
    {
        $this->seed(CategorySeeder::class);

        $food = Category::whereNull('user_id')->where('name', 'Food')->firstOrFail();

        $this->assertTrue($food->isSystem());
        $this->assertTrue($food->ownedBy(1));
        $this->assertTrue($food->ownedBy(9999));
    }
}
