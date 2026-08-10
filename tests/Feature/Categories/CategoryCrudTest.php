<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_users_own_categories_and_system_categories(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'My Category']);
        Category::factory()->for($other)->create(['name' => 'Their Category']);
        Category::factory()->system()->create(['name' => 'System Category']);

        $response = $this->actingAs($user)->get('/categories');

        $response->assertOk();
        $response->assertSee('My Category');
        $response->assertSee('System Category');
        $response->assertDontSee('Their Category');
    }

    public function test_a_user_can_create_a_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Hobbies',
            'category_type' => 'EXPENSE',
            'parent_id' => null,
            'icon' => null,
        ]);

        $response->assertRedirect(route('categories.index'));
        $category = Category::where('name', 'Hobbies')->firstOrFail();
        $this->assertSame($user->id, $category->user_id);
        $this->assertTrue($category->is_active);
    }

    public function test_a_user_can_edit_their_own_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'New Name',
            'category_type' => $category->category_type,
            'parent_id' => null,
            'icon' => null,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertSame('New Name', $category->fresh()->name);
    }

    public function test_a_system_category_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $system = Category::factory()->system()->create(['name' => 'Food']);

        $this->actingAs($user)->get(route('categories.edit', $system))->assertForbidden();

        $this->actingAs($user)->put(route('categories.update', $system), [
            'name' => 'Hijacked',
            'category_type' => 'EXPENSE',
        ])->assertForbidden();

        $this->assertSame('Food', $system->fresh()->name);
    }

    public function test_a_system_category_cannot_be_deactivated(): void
    {
        $user = User::factory()->create();
        $system = Category::factory()->system()->create();

        $this->actingAs($user)->patch(route('categories.deactivate', $system))->assertForbidden();

        $this->assertTrue($system->fresh()->is_active);
    }

    public function test_deactivating_a_category_never_deletes_it(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('categories.deactivate', $category));

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_a_category_can_have_another_category_owned_by_the_same_user_as_its_parent(): void
    {
        $user = User::factory()->create();
        $parent = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Child',
            'category_type' => 'EXPENSE',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertSame($parent->id, Category::where('name', 'Child')->firstOrFail()->parent_id);
    }

    public function test_a_category_can_have_a_null_parent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Top Level',
            'category_type' => 'EXPENSE',
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertNull(Category::where('name', 'Top Level')->firstOrFail()->parent_id);
    }

    public function test_a_category_cannot_use_another_users_category_as_its_parent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $othersCategory = Category::factory()->for($other)->create();

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Should Fail',
            'category_type' => 'EXPENSE',
            'parent_id' => $othersCategory->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('categories', ['name' => 'Should Fail']);
    }

    public function test_a_category_cannot_use_a_system_category_as_its_parent(): void
    {
        $user = User::factory()->create();
        $system = Category::factory()->system()->create();

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Should Also Fail',
            'category_type' => 'EXPENSE',
            'parent_id' => $system->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('categories', ['name' => 'Should Also Fail']);
    }

    public function test_updating_a_category_rejects_itself_as_its_own_parent(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Self Parent']);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'Self Parent',
            'category_type' => $category->category_type,
            'parent_id' => $category->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_updating_a_category_accepts_another_owned_category_as_its_parent(): void
    {
        $user = User::factory()->create();
        $parent = Category::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['name' => 'Child', 'parent_id' => null]);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'Child',
            'category_type' => $category->category_type,
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertSame($parent->id, $category->fresh()->parent_id);
    }

    public function test_updating_a_category_rejects_another_users_category_as_its_parent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $othersCategory = Category::factory()->for($other)->create();
        $category = Category::factory()->for($user)->create(['parent_id' => null]);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => $category->name,
            'category_type' => $category->category_type,
            'parent_id' => $othersCategory->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_updating_a_category_rejects_a_system_category_as_its_parent(): void
    {
        $user = User::factory()->create();
        $system = Category::factory()->system()->create();
        $category = Category::factory()->for($user)->create(['parent_id' => null]);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => $category->name,
            'category_type' => $category->category_type,
            'parent_id' => $system->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertNull($category->fresh()->parent_id);
    }
}
