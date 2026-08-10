<?php

namespace Tests\Feature\Obligations;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObligationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeObligation(User $user, string $key): PaymentObligation
    {
        $category = Category::factory()->for($user)->create();

        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_a_guest_is_redirected_to_login_from_every_obligation_route(): void
    {
        $owner = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-guest');

        $this->get('/obligations')->assertRedirect('/login');
        $this->get('/obligations/create')->assertRedirect('/login');
        $this->post('/obligations', [])->assertRedirect('/login');
        $this->post('/obligations/generate')->assertRedirect('/login');
        $this->get(route('obligations.show', $obligation))->assertRedirect('/login');
        $this->patch(route('obligations.skip', $obligation))->assertRedirect('/login');
        $this->patch(route('obligations.cancel', $obligation))->assertRedirect('/login');
    }

    public function test_a_user_cannot_view_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-view');

        $this->actingAs($attacker)->get(route('obligations.show', $obligation))->assertForbidden();
    }

    public function test_a_user_cannot_skip_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-skip');

        $this->actingAs($attacker)->patch(route('obligations.skip', $obligation))->assertForbidden();

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_a_user_cannot_cancel_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-cancel');

        $this->actingAs($attacker)->patch(route('obligations.cancel', $obligation))->assertForbidden();

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_a_spoofed_category_id_on_one_time_creation_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $obligationCount = PaymentObligation::count();

        $response = $this->actingAs($attacker)->post(route('obligations.store'), [
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertSame($obligationCount, PaymentObligation::count());
    }

    public function test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerCategory = Category::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $obligationCount = PaymentObligation::count();

        $response = $this->actingAs($attacker)->post(route('obligations.store'), [
            'category_id' => $attackerCategory->id,
            'planned_account_id' => $ownerAccount->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertSame($obligationCount, PaymentObligation::count());
    }

    /**
     * Phase 4 Decision Package v1.1.0 P1 correction: planned_account_id
     * ownership must be authoritative at the PaymentObligationService
     * boundary, not only at the HTTP controller layer -- proven here by
     * bypassing the controller/Form Request entirely and calling the
     * service directly.
     */
    public function test_a_user_cannot_directly_invoke_create_one_time_with_another_users_planned_account_id(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerCategory = Category::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $service = new PaymentObligationService(new OwnershipGuard);

        $this->expectException(OwnershipViolationException::class);

        try {
            $service->createOneTime(
                $attacker,
                (string) Str::uuid(),
                $attackerCategory,
                '2026-01-01',
                '2026-01-31',
                '2026-01-15',
                '1000.00',
                true,
                $ownerAccount->id,
            );
        } finally {
            $this->assertSame(0, PaymentObligation::count());
        }
    }

    public function test_create_one_time_succeeds_with_the_same_users_own_planned_account_id(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $account = Account::factory()->for($user)->create();
        $service = new PaymentObligationService(new OwnershipGuard);

        $obligation = $service->createOneTime(
            $user,
            (string) Str::uuid(),
            $category,
            '2026-01-01',
            '2026-01-31',
            '2026-01-15',
            '1000.00',
            true,
            $account->id,
        );

        $this->assertSame($account->id, $obligation->planned_account_id);
        $this->assertSame(1, PaymentObligation::count());
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)->post(route('obligations.store'), [
            'user_id' => $victim->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $obligation = PaymentObligation::where('category_id', $category->id)->firstOrFail();
        $this->assertSame($user->id, $obligation->user_id);
        $this->assertNotSame($victim->id, $obligation->user_id);
    }
}
