<?php

namespace Tests\Feature\RecurringTemplates;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTemplateOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_from_every_recurring_template_route(): void
    {
        $template = RecurringPaymentTemplate::factory()->create(['due_rule' => 'DAY:5']);

        $this->get('/recurring-templates')->assertRedirect('/login');
        $this->get('/recurring-templates/create')->assertRedirect('/login');
        $this->post('/recurring-templates', [])->assertRedirect('/login');
        $this->get(route('recurring-templates.show', $template))->assertRedirect('/login');
        $this->get(route('recurring-templates.edit', $template))->assertRedirect('/login');
        $this->put(route('recurring-templates.update', $template), [])->assertRedirect('/login');
        $this->patch(route('recurring-templates.cancel', $template))->assertRedirect('/login');
    }

    public function test_a_user_cannot_view_another_users_template(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = Category::factory()->for($owner)->create();
        $template = RecurringPaymentTemplate::factory()->for($owner)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
        ]);

        $this->actingAs($attacker)->get(route('recurring-templates.show', $template))->assertForbidden();
    }

    public function test_a_user_cannot_edit_another_users_template(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = Category::factory()->for($owner)->create();
        $template = RecurringPaymentTemplate::factory()->for($owner)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
            'name' => 'Owner Template',
        ]);

        $this->actingAs($attacker)->get(route('recurring-templates.edit', $template))->assertForbidden();

        $this->actingAs($attacker)->put(route('recurring-templates.update', $template), [
            'name' => 'Hijacked',
            'amount' => $template->amount,
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'category_id' => $category->id,
            'starts_on' => $template->starts_on->toDateString(),
        ])->assertForbidden();

        $this->assertSame('Owner Template', $template->fresh()->name);
    }

    public function test_a_user_cannot_cancel_another_users_template(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $category = Category::factory()->for($owner)->create();
        $template = RecurringPaymentTemplate::factory()->for($owner)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
        ]);

        $this->actingAs($attacker)->patch(route('recurring-templates.cancel', $template))->assertForbidden();

        $this->assertSame('ACTIVE', $template->fresh()->status);
    }

    public function test_a_spoofed_category_id_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $templateCount = RecurringPaymentTemplate::count();

        $response = $this->actingAs($attacker)->post(route('recurring-templates.store'), [
            'name' => 'Attack',
            'amount' => '100.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'category_id' => $ownerCategory->id,
            'starts_on' => '2026-01-01',
        ]);

        $response->assertForbidden();
        $this->assertSame($templateCount, RecurringPaymentTemplate::count());
    }

    public function test_a_spoofed_default_account_id_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerCategory = Category::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $templateCount = RecurringPaymentTemplate::count();

        $response = $this->actingAs($attacker)->post(route('recurring-templates.store'), [
            'name' => 'Attack',
            'amount' => '100.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'category_id' => $attackerCategory->id,
            'default_account_id' => $ownerAccount->id,
            'starts_on' => '2026-01-01',
        ]);

        $response->assertForbidden();
        $this->assertSame($templateCount, RecurringPaymentTemplate::count());
    }
}
