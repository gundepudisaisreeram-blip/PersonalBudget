<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_from_every_category_route(): void
    {
        $category = Category::factory()->create();

        $this->get('/categories')->assertRedirect('/login');
        $this->get('/categories/create')->assertRedirect('/login');
        $this->post('/categories', [])->assertRedirect('/login');
        $this->get(route('categories.edit', $category))->assertRedirect('/login');
        $this->put(route('categories.update', $category), [])->assertRedirect('/login');
        $this->patch(route('categories.deactivate', $category))->assertRedirect('/login');
    }

    public function test_a_user_cannot_edit_another_users_category_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = Category::factory()->for($owner)->create(['name' => 'Owner Category']);

        $this->actingAs($attacker)->get(route('categories.edit', $category))->assertForbidden();

        $this->actingAs($attacker)->put(route('categories.update', $category), [
            'name' => 'Hijacked',
            'category_type' => $category->category_type,
        ])->assertForbidden();

        $this->assertSame('Owner Category', $category->fresh()->name);
    }

    public function test_a_user_cannot_deactivate_another_users_category_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        $this->actingAs($attacker)->patch(route('categories.deactivate', $category))->assertForbidden();

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user)->post('/categories', [
            'user_id' => $victim->id,
            'name' => 'Spoofed Owner Category',
            'category_type' => 'EXPENSE',
        ]);

        $category = Category::where('name', 'Spoofed Owner Category')->firstOrFail();
        $this->assertSame($user->id, $category->user_id);
        $this->assertNotSame($victim->id, $category->user_id);
    }
}
