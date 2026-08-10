<?php

namespace Tests\Feature\Transactions;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new TransactionService(new OwnershipGuard);
    }

    public function test_a_guest_is_redirected_to_login_from_every_transaction_route(): void
    {
        $owner = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $transaction = $this->transactions->recordExpense($owner, $account, '100.00', '2026-01-05', 'Groceries');

        $this->get('/transactions')->assertRedirect('/login');
        $this->get('/transactions/create')->assertRedirect('/login');
        $this->get(route('transactions.show', $transaction))->assertRedirect('/login');

        $this->get('/transactions/expense')->assertRedirect('/login');
        $this->post('/transactions/expense', [])->assertRedirect('/login');
        $this->get('/transactions/income')->assertRedirect('/login');
        $this->post('/transactions/income', [])->assertRedirect('/login');
        $this->get('/transactions/transfer')->assertRedirect('/login');
        $this->post('/transactions/transfer', [])->assertRedirect('/login');
        $this->get('/transactions/refund')->assertRedirect('/login');
        $this->post('/transactions/refund', [])->assertRedirect('/login');
        $this->get('/transactions/reversal')->assertRedirect('/login');
        $this->post('/transactions/reversal', [])->assertRedirect('/login');
        $this->get('/transactions/adjustment')->assertRedirect('/login');
        $this->post('/transactions/adjustment', [])->assertRedirect('/login');
    }

    public function test_a_user_cannot_view_another_users_transaction_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();
        $transaction = $this->transactions->recordExpense($owner, $account, '100.00', '2026-01-05', 'Groceries');

        $this->actingAs($attacker)->get(route('transactions.show', $transaction))->assertForbidden();
    }

    public function test_a_spoofed_expense_account_id_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.expense.store'), [
            'account_id' => $ownerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Attack',
        ]);

        $response->assertForbidden();
        $response->assertDontSee('belong', false);
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_expense_category_id_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.expense.store'), [
            'account_id' => $attackerAccount->id,
            'category_id' => $ownerCategory->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Attack',
        ]);

        $response->assertForbidden();
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_transfer_from_account_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.transfer.store'), [
            'from_account_id' => $ownerAccount->id,
            'to_account_id' => $attackerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Attack',
        ]);

        $response->assertForbidden();
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_transfer_to_account_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.transfer.store'), [
            'from_account_id' => $attackerAccount->id,
            'to_account_id' => $ownerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Attack',
        ]);

        $response->assertForbidden();
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_refund_destination_account_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $attackerExpense = $this->transactions->recordExpense($attacker, $attackerAccount, '100.00', '2026-01-05', 'Purchase');
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $attackerExpense->id,
            'account_id' => $ownerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Attack',
        ]);

        $response->assertForbidden();
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_adjustment_account_belonging_to_another_user_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.adjustment.store'), [
            'account_id' => $ownerAccount->id,
            'direction' => 'OUTFLOW',
            'amount' => '25.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Attack',
            'reason' => 'Attack',
        ]);

        $response->assertForbidden();
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transactions.expense.store'), [
            'user_id' => $victim->id,
            'account_id' => $account->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Spoofed owner test',
        ]);

        $transaction = Transaction::where('description', 'Spoofed owner test')->firstOrFail();
        $this->assertSame($user->id, $transaction->user_id);
        $this->assertNotSame($victim->id, $transaction->user_id);
    }
}
