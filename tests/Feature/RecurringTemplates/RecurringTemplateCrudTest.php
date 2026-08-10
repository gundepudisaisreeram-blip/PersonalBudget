<?php

namespace Tests\Feature\RecurringTemplates;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_recurring_template(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('recurring-templates.store'), [
            'name' => 'Netflix',
            'amount' => '649.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'category_id' => $category->id,
            'starts_on' => '2026-01-01',
            'is_mandatory' => '1',
        ]);

        $template = RecurringPaymentTemplate::where('name', 'Netflix')->firstOrFail();
        $response->assertRedirect(route('recurring-templates.show', $template));
        $this->assertSame($user->id, $template->user_id);
        $this->assertSame('ACTIVE', $template->status);
    }

    public function test_creating_a_template_rejects_a_malformed_due_rule(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('recurring-templates.store'), [
            'name' => 'Bad Rule',
            'amount' => '100.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY_OF_MONTH:5',
            'category_id' => $category->id,
            'starts_on' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('due_rule');
        $this->assertDatabaseMissing('recurring_payment_templates', ['name' => 'Bad Rule']);
    }

    public function test_creating_a_template_rejects_a_non_monthly_frequency(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('recurring-templates.store'), [
            'name' => 'Weekly Thing',
            'amount' => '100.00',
            'frequency' => 'WEEKLY',
            'due_rule' => 'DAY:5',
            'category_id' => $category->id,
            'starts_on' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('frequency');
    }

    public function test_a_user_can_edit_their_own_template(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->put(route('recurring-templates.update', $template), [
            'name' => 'New Name',
            'amount' => $template->amount,
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:10',
            'category_id' => $category->id,
            'starts_on' => $template->starts_on->toDateString(),
        ]);

        $response->assertRedirect(route('recurring-templates.show', $template));
        $template->refresh();
        $this->assertSame('New Name', $template->name);
        $this->assertSame('DAY:10', $template->due_rule);
    }

    public function test_a_user_can_cancel_an_active_template(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
        ]);

        $response = $this->actingAs($user)->patch(route('recurring-templates.cancel', $template));

        $response->assertRedirect(route('recurring-templates.show', $template));
        $this->assertSame('CANCELLED', $template->fresh()->status);
    }

    public function test_cancelling_a_template_never_deletes_it_or_its_historical_obligations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create([
            'category_id' => $category->id,
            'due_rule' => 'DAY:5',
        ]);

        $this->actingAs($user)->patch(route('recurring-templates.cancel', $template));

        $this->assertDatabaseHas('recurring_payment_templates', ['id' => $template->id]);
    }

    public function test_a_template_with_a_default_account_can_be_created(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('recurring-templates.store'), [
            'name' => 'Home Loan',
            'amount' => '65000.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:31',
            'category_id' => $category->id,
            'default_account_id' => $account->id,
            'starts_on' => '2026-01-01',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $template = RecurringPaymentTemplate::where('name', 'Home Loan')->firstOrFail();
        $this->assertSame($account->id, $template->default_account_id);
    }
}
