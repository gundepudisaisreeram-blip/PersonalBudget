<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_from_every_account_route(): void
    {
        $account = Account::factory()->create();

        $this->get('/accounts')->assertRedirect('/login');
        $this->get('/accounts/create')->assertRedirect('/login');
        $this->post('/accounts', [])->assertRedirect('/login');
        $this->get(route('accounts.show', $account))->assertRedirect('/login');
        $this->get(route('accounts.edit', $account))->assertRedirect('/login');
        $this->put(route('accounts.update', $account), [])->assertRedirect('/login');
        $this->patch(route('accounts.close', $account))->assertRedirect('/login');
    }

    public function test_a_user_cannot_view_another_users_account_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();

        $this->actingAs($attacker)->get(route('accounts.show', $account))->assertForbidden();
    }

    public function test_a_user_cannot_edit_another_users_account_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();

        $this->actingAs($attacker)->get(route('accounts.edit', $account))->assertForbidden();

        $this->actingAs($attacker)->put(route('accounts.update', $account), [
            'name' => 'Hijacked',
            'institution' => $account->institution,
            'account_type' => $account->account_type,
            'subtype' => $account->subtype,
            'currency' => $account->currency,
            'opening_balance' => $account->opening_balance,
            'opening_balance_date' => $account->opening_balance_date->toDateString(),
        ])->assertForbidden();

        $this->assertSame($account->name, $account->fresh()->name);
    }

    public function test_a_user_cannot_close_another_users_account_by_substituting_the_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create();

        $this->actingAs($attacker)->patch(route('accounts.close', $account))->assertForbidden();

        $this->assertSame('ACTIVE', $account->fresh()->status);
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user)->post('/accounts', [
            'user_id' => $victim->id,
            'name' => 'Spoofed Owner',
            'institution' => 'HDFC',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
        ]);

        $account = Account::where('name', 'Spoofed Owner')->firstOrFail();
        $this->assertSame($user->id, $account->user_id);
        $this->assertNotSame($victim->id, $account->user_id);
    }
}
