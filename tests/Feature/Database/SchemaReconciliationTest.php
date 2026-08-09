<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Proves the corrections made during the 04-vs-09 specification reconciliation
 * (2026-08-09): direct user_id -> users.id FKs on the three composite-tenant-FK
 * tables, and accounts.institution / accounts.subtype / accounts.currency.
 */
class SchemaReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string}>
     */
    public static function tablesRequiringDirectUserForeignKey(): array
    {
        return [
            ['ledger_entries'],
            ['obligation_allocations'],
            ['reconciliation_matches'],
        ];
    }

    #[DataProvider('tablesRequiringDirectUserForeignKey')]
    public function test_table_has_a_direct_foreign_key_from_user_id_to_users(string $table): void
    {
        $constraints = DB::select(
            <<<'SQL'
                SELECT COUNT(*) as total
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'user_id'
                  AND REFERENCED_TABLE_NAME = 'users'
                  AND REFERENCED_COLUMN_NAME = 'id'
            SQL,
            [$table],
        );

        $this->assertSame(1, (int) $constraints[0]->total, "Expected a direct single-column FK from {$table}.user_id to users.id.");
    }

    public function test_accounts_institution_is_required(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        Account::create([
            'user_id' => $user->id,
            'name' => 'No Institution',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_accounts_subtype_is_required(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        Account::create([
            'user_id' => $user->id,
            'name' => 'No Subtype',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_accounts_currency_is_not_artificially_length_restricted(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Long Currency Code',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'XTEST',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('XTEST', $account->currency);
    }
}
