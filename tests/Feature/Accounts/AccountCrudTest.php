<?php

namespace Tests\Feature\Accounts;

use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_own_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Account::factory()->for($user)->create(['name' => 'My Bank']);
        Account::factory()->for($other)->create(['name' => 'Their Bank']);

        $response = $this->actingAs($user)->get('/accounts');

        $response->assertOk();
        $response->assertSee('My Bank');
        $response->assertDontSee('Their Bank');
    }

    public function test_a_user_can_create_an_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => 'Savings',
            'institution' => 'HDFC',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '1000.00',
            'opening_balance_date' => '2026-01-01',
            'notes' => null,
        ]);

        $account = Account::where('name', 'Savings')->firstOrFail();
        $response->assertRedirect(route('accounts.show', $account));
        $this->assertSame($user->id, $account->user_id);
        $this->assertSame('ACTIVE', $account->status);
    }

    public function test_creating_an_account_rejects_a_negative_opening_balance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => 'Bad Account',
            'institution' => 'HDFC',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '-100.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('opening_balance');
        $this->assertDatabaseMissing('accounts', ['name' => 'Bad Account']);
    }

    public function test_creating_an_account_requires_institution_and_subtype(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => 'Incomplete Account',
            'account_type' => 'ASSET',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['institution', 'subtype']);
    }

    public function test_show_displays_the_balance_from_account_balance_service(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);
        $transactions = new TransactionService(new OwnershipGuard);
        $transactions->recordExpense($user, $account, '150.00', '2026-01-05', 'Groceries');

        $expectedBalance = app(AccountBalanceService::class)->calculate($account->fresh());

        $response = $this->actingAs($user)->get(route('accounts.show', $account));

        $response->assertOk();
        $response->assertSee($expectedBalance);
        $this->assertSame('850.00', $expectedBalance);
    }

    public function test_a_user_can_edit_an_active_accounts_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'account_type' => 'ASSET',
            'currency' => 'INR',
            'opening_balance' => '500.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Renamed',
            'institution' => 'New Bank',
            'account_type' => 'LIABILITY',
            'subtype' => 'CREDIT_CARD',
            'currency' => 'USD',
            'opening_balance' => '750.00',
            'opening_balance_date' => '2026-02-01',
            'notes' => 'Updated',
        ]);

        $response->assertRedirect(route('accounts.show', $account));
        $account->refresh();
        $this->assertSame('Renamed', $account->name);
        $this->assertSame('LIABILITY', $account->account_type);
        $this->assertSame('USD', $account->currency);
        $this->assertSame('750.00', $account->opening_balance);
    }

    public function test_a_user_can_close_an_active_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('accounts.close', $account));

        $response->assertRedirect(route('accounts.show', $account));
        $this->assertSame('CLOSED', $account->fresh()->status);
    }

    public function test_closing_an_account_never_deletes_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->patch(route('accounts.close', $account));

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_a_closed_accounts_cosmetic_fields_remain_editable(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create([
            'name' => 'Old Name',
            'institution' => 'Old Bank',
            'subtype' => 'SAVINGS',
            'notes' => 'Old notes',
        ]);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'New Name',
            'institution' => 'New Bank',
            'account_type' => $account->account_type,
            'subtype' => 'CURRENT',
            'currency' => $account->currency,
            'opening_balance' => $account->opening_balance,
            'opening_balance_date' => $account->opening_balance_date->toDateString(),
            'notes' => 'New notes',
        ]);

        $response->assertRedirect(route('accounts.show', $account));
        $account->refresh();
        $this->assertSame('New Name', $account->name);
        $this->assertSame('New Bank', $account->institution);
        $this->assertSame('CURRENT', $account->subtype);
        $this->assertSame('New notes', $account->notes);
    }

    public function test_a_closed_accounts_financial_fields_cannot_be_changed_via_direct_http_manipulation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create([
            'account_type' => 'ASSET',
            'currency' => 'INR',
            'opening_balance' => '1000.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        // A malicious/manipulated request attempts to change every frozen field directly.
        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => $account->name,
            'institution' => $account->institution,
            'subtype' => $account->subtype,
            'notes' => $account->notes,
            'account_type' => 'LIABILITY',
            'currency' => 'USD',
            'opening_balance' => '999999.00',
            'opening_balance_date' => '2020-01-01',
        ]);

        $response->assertSessionHasErrors(['account_type', 'currency', 'opening_balance', 'opening_balance_date']);

        $account->refresh();
        $this->assertSame('ASSET', $account->account_type);
        $this->assertSame('INR', $account->currency);
        $this->assertSame('1000.00', $account->opening_balance);
        $this->assertSame('2026-01-01', $account->opening_balance_date->toDateString());
    }

    public function test_the_update_request_freezes_the_exact_fields_specified_by_the_governance_contract(): void
    {
        $this->assertSame(
            ['account_type', 'opening_balance', 'opening_balance_date', 'currency'],
            UpdateAccountRequest::FROZEN_WHEN_CLOSED,
        );
    }
}
