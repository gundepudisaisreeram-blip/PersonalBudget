# PHASE 1 IMPLEMENTATION SOURCE — SOURCE BUNDLE

Concatenation of the actual PHP/config source files that implement Phase 1, verbatim.


---

## FILE: database/migrations/0001_01_01_000000_create_users_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```


---

## FILE: database/migrations/0001_01_01_000001_create_cache_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
```


---

## FILE: database/migrations/0001_01_01_000002_create_jobs_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000010_create_categories_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('category_type');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000020_create_accounts_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('institution');
            $table->enum('account_type', ['ASSET', 'LIABILITY']);
            $table->string('subtype');
            $table->string('currency')->default('INR');
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->date('opening_balance_date');
            $table->enum('status', ['ACTIVE', 'CLOSED'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->index('user_id');
            $table->index(['user_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE accounts ADD CONSTRAINT chk_accounts_opening_balance_non_negative CHECK (opening_balance >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000030_create_category_rules_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->integer('priority')->default(0);
            $table->string('match_type');
            $table->string('match_value');
            $table->string('bank')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000040_create_budgets_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('budget_amount', 15, 2);
            $table->boolean('is_mandatory_reserve')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
        });

        DB::statement(
            'ALTER TABLE budgets ADD CONSTRAINT chk_budgets_amount_non_negative CHECK (budget_amount >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000050_create_recurring_payment_templates_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_payment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('frequency');
            $table->string('due_rule');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('status', ['ACTIVE', 'CANCELLED'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_payment_templates');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000060_create_payment_obligations_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recurring_payment_template_id')->nullable()
                ->constrained('recurring_payment_templates')->restrictOnDelete();
            $table->string('occurrence_key')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('planned_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->decimal('planned_amount', 15, 2);
            $table->enum('status', ['PENDING', 'PARTIALLY_PAID', 'PAID', 'SKIPPED', 'CANCELLED'])
                ->default('PENDING');
            $table->boolean('is_mandatory')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->unique(['recurring_payment_template_id', 'occurrence_key'], 'payment_obligations_template_occurrence_unique');
            $table->unique('idempotency_key');
            $table->index('user_id');
            $table->index('category_id');
            $table->index('due_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payment_obligations ADD CONSTRAINT chk_payment_obligations_identity CHECK (
                (
                    recurring_payment_template_id IS NOT NULL
                    AND occurrence_key IS NOT NULL
                    AND idempotency_key IS NULL
                )
                OR
                (
                    recurring_payment_template_id IS NULL
                    AND occurrence_key IS NULL
                    AND idempotency_key IS NOT NULL
                )
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_obligations');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000070_create_transactions_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('transaction_date');
            $table->enum('transaction_type', ['EXPENSE', 'INCOME', 'TRANSFER', 'REFUND', 'REVERSAL', 'ADJUSTMENT']);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->enum('source', ['MANUAL', 'BANK_IMPORT', 'ADJUSTMENT', 'SYSTEM']);
            $table->enum('status', ['POSTED'])->default('POSTED');
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('parent_transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'transaction_type']);
            $table->index('parent_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000080_create_ledger_entries_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('account_id');
            $table->enum('direction', ['INFLOW', 'OUTFLOW']);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('account_id');
            $table->index(['user_id', 'transaction_id']);
            $table->index(['user_id', 'account_id']);

            $table->foreign(['user_id', 'transaction_id'])
                ->references(['user_id', 'id'])->on('transactions')
                ->restrictOnDelete();

            $table->foreign(['user_id', 'account_id'])
                ->references(['user_id', 'id'])->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_entries_amount_positive CHECK (amount > 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000090_create_obligation_allocations_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('obligation_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('payment_obligation_id');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();

            $table->index('payment_obligation_id');
            $table->index('transaction_id');
            $table->index(['user_id', 'payment_obligation_id']);
            $table->index(['user_id', 'transaction_id']);

            $table->foreign(['user_id', 'payment_obligation_id'])
                ->references(['user_id', 'id'])->on('payment_obligations')
                ->restrictOnDelete();

            $table->foreign(['user_id', 'transaction_id'])
                ->references(['user_id', 'id'])->on('transactions')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE obligation_allocations ADD CONSTRAINT chk_obligation_allocations_amount_positive CHECK (allocated_amount > 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligation_allocations');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000100_create_statement_imports_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('bank');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('file_hash');
            $table->string('file_type');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->integer('transaction_count')->default(0);
            $table->enum('status', [
                'UPLOADING', 'PARSING', 'VALIDATING', 'CATEGORIZING',
                'MATCHING', 'READY_FOR_REVIEW', 'FAILED', 'COMPLETED',
            ]);
            $table->string('parser_version')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'file_hash']);
            $table->index(['user_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_imports');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000110_create_statement_transactions_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statement_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('statement_import_id')->constrained('statement_imports')->restrictOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('description');
            $table->string('reference')->nullable();
            $table->decimal('normalized_amount', 15, 2);
            $table->enum('direction', ['INFLOW', 'OUTFLOW']);
            $table->decimal('statement_balance', 15, 2)->nullable();
            $table->string('normalized_hash');
            $table->json('raw_data')->nullable();
            $table->enum('processing_status', [
                'STAGED', 'PROCESSING', 'READY_FOR_REVIEW', 'COMMITTED', 'IGNORED', 'FAILED',
            ]);
            $table->enum('categorization_status', ['UNCATEGORIZED', 'SUGGESTED', 'CONFIRMED']);
            $table->enum('match_status', ['UNMATCHED', 'SUGGESTED', 'CONFIRMED']);
            $table->enum('duplicate_status', ['UNIQUE', 'POTENTIAL_DUPLICATE', 'CONFIRMED_DUPLICATE']);
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->integer('confidence_score')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->index('statement_import_id');
            $table->index(['user_id', 'transaction_date']);
            $table->index('normalized_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_transactions');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000120_create_reconciliation_matches_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('statement_transaction_id');
            $table->unsignedBigInteger('transaction_id');
            $table->string('match_type');
            $table->integer('confidence_score')->nullable();
            $table->string('explanation')->nullable();
            $table->enum('status', ['SUGGESTED', 'CONFIRMED', 'REJECTED']);
            $table->timestamps();

            $table->unique('statement_transaction_id');
            $table->index('transaction_id');
            $table->index(['user_id', 'transaction_id']);

            $table->foreign(['user_id', 'statement_transaction_id'])
                ->references(['user_id', 'id'])->on('statement_transactions')
                ->restrictOnDelete();

            $table->foreign(['user_id', 'transaction_id'])
                ->references(['user_id', 'id'])->on('transactions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_matches');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000130_create_account_reconciliations_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('statement_import_id')->nullable()->constrained('statement_imports')->restrictOnDelete();
            $table->date('reconciliation_date');
            $table->decimal('ledger_balance', 15, 2);
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->enum('status', ['MATCHED', 'MISMATCH', 'RESOLVED', 'NOT_VALIDATABLE']);
            $table->text('resolution_note')->nullable();
            $table->foreignId('adjustment_transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->timestamps();

            $table->index('account_id');
            $table->index('statement_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_reconciliations');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000140_create_audit_logs_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```


---

## FILE: database/migrations/2025_01_02_000150_create_settings_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```


---

## FILE: database/seeders/CategorySeeder.php

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * System/default categories are shared across all users (user_id = NULL).
     * Seeding is idempotent: re-running never creates duplicates.
     *
     * @var list<array{name: string, category_type: string, icon: string|null}>
     */
    private const SYSTEM_CATEGORIES = [
        ['name' => 'Food', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Transport', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Shopping', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Loans', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Subscriptions', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Insurance', 'category_type' => 'EXPENSE', 'icon' => null],
        ['name' => 'Investments', 'category_type' => 'INVESTMENT', 'icon' => null],
        ['name' => 'Income', 'category_type' => 'INCOME', 'icon' => null],
    ];

    /**
     * Seed the application's system/default categories.
     */
    public function run(): void
    {
        foreach (self::SYSTEM_CATEGORIES as $category) {
            Category::query()->firstOrCreate(
                [
                    'user_id' => null,
                    'name' => $category['name'],
                ],
                [
                    'category_type' => $category['category_type'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
```


---

## FILE: database/seeders/DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only system-level defaults are seeded here. Mock accounts, transactions,
     * payments, statements, and balances must never be seeded into production.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
    }
}
```


---

## FILE: database/factories/AccountFactory.php

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'institution' => fake()->company(),
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => now()->subYear()->toDateString(),
            'status' => 'ACTIVE',
        ];
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'LIABILITY',
            'subtype' => 'CREDIT_CARD',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CLOSED',
        ]);
    }
}
```


---

## FILE: database/factories/CategoryFactory.php

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'category_type' => 'EXPENSE',
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
```


---

## FILE: database/factories/RecurringPaymentTemplateFactory.php

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPaymentTemplate>
 */
class RecurringPaymentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'amount' => '1000.00',
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY_OF_MONTH:5',
            'category_id' => Category::factory(),
            'is_mandatory' => true,
            'starts_on' => now()->subYear()->toDateString(),
            'status' => 'ACTIVE',
        ];
    }
}
```


---

## FILE: database/factories/UserFactory.php

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```


---

## FILE: app/Models/Account.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'institution', 'account_type', 'subtype', 'currency',
    'opening_balance', 'opening_balance_date', 'status', 'notes',
])]
class Account extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
        ];
    }

    public function isAsset(): bool
    {
        return $this->account_type === 'ASSET';
    }

    public function isLiability(): bool
    {
        return $this->account_type === 'LIABILITY';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function statementImports(): HasMany
    {
        return $this->hasMany(StatementImport::class);
    }

    public function accountReconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }
}
```


---

## FILE: app/Models/AccountReconciliation.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'account_id', 'statement_import_id', 'reconciliation_date', 'ledger_balance',
    'statement_balance', 'difference', 'status', 'resolution_note', 'adjustment_transaction_id',
])]
class AccountReconciliation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'ledger_balance' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }
}
```


---

## FILE: app/Models/AuditLog.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values',
    'metadata', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```


---

## FILE: app/Models/Budget.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'category_id', 'period_start', 'period_end', 'budget_amount', 'is_mandatory_reserve',
])]
class Budget extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'budget_amount' => 'decimal:2',
            'is_mandatory_reserve' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```


---

## FILE: app/Models/Category.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'category_type', 'parent_id', 'icon', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * System categories have a NULL user_id and are shared across all users.
     */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    public function ownedBy(int $userId): bool
    {
        return $this->isSystem() || $this->user_id === $userId;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recurringPaymentTemplates(): HasMany
    {
        return $this->hasMany(RecurringPaymentTemplate::class);
    }

    public function paymentObligations(): HasMany
    {
        return $this->hasMany(PaymentObligation::class);
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
```


---

## FILE: app/Models/CategoryRule.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'name', 'priority', 'match_type', 'match_value', 'bank',
    'account_id', 'category_id', 'is_active',
])]
class CategoryRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```


---

## FILE: app/Models/LedgerEntry.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'transaction_id', 'account_id', 'direction', 'amount'])]
class LedgerEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function isInflow(): bool
    {
        return $this->direction === 'INFLOW';
    }

    public function isOutflow(): bool
    {
        return $this->direction === 'OUTFLOW';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
```


---

## FILE: app/Models/ObligationAllocation.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'payment_obligation_id', 'transaction_id', 'allocated_amount'])]
class ObligationAllocation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentObligation(): BelongsTo
    {
        return $this->belongsTo(PaymentObligation::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
```


---

## FILE: app/Models/PaymentObligation.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'recurring_payment_template_id', 'occurrence_key', 'idempotency_key',
    'category_id', 'planned_account_id', 'period_start', 'period_end', 'due_date',
    'planned_amount', 'status', 'is_mandatory', 'notes',
])]
class PaymentObligation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'planned_amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
        ];
    }

    public function isRecurring(): bool
    {
        return $this->recurring_payment_template_id !== null;
    }

    public function isOneTime(): bool
    {
        return $this->idempotency_key !== null;
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['SKIPPED', 'CANCELLED'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurringPaymentTemplate(): BelongsTo
    {
        return $this->belongsTo(RecurringPaymentTemplate::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function plannedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'planned_account_id');
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }
}
```


---

## FILE: app/Models/ReconciliationMatch.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'statement_transaction_id', 'transaction_id', 'match_type',
    'confidence_score', 'explanation', 'status',
])]
class ReconciliationMatch extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statementTransaction(): BelongsTo
    {
        return $this->belongsTo(StatementTransaction::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
```


---

## FILE: app/Models/RecurringPaymentTemplate.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'amount', 'frequency', 'due_rule', 'category_id', 'default_account_id',
    'is_mandatory', 'starts_on', 'ends_on', 'status', 'notes',
])]
class RecurringPaymentTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function paymentObligations(): HasMany
    {
        return $this->hasMany(PaymentObligation::class);
    }
}
```


---

## FILE: app/Models/Setting.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'key', 'value'])]
class Setting extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```


---

## FILE: app/Models/StatementImport.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'account_id', 'bank', 'original_filename', 'storage_path', 'file_hash', 'file_type',
    'period_from', 'period_to', 'opening_balance', 'closing_balance', 'transaction_count',
    'status', 'parser_version', 'error_message',
])]
class StatementImport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statementTransactions(): HasMany
    {
        return $this->hasMany(StatementTransaction::class);
    }

    public function accountReconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }
}
```


---

## FILE: app/Models/StatementTransaction.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'statement_import_id', 'transaction_date', 'value_date', 'description', 'reference',
    'normalized_amount', 'direction', 'statement_balance', 'normalized_hash', 'raw_data',
    'processing_status', 'categorization_status', 'match_status', 'duplicate_status',
    'suggested_category_id', 'confidence_score',
])]
class StatementTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'normalized_amount' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function reconciliationMatch(): HasOne
    {
        return $this->hasOne(ReconciliationMatch::class);
    }
}
```


---

## FILE: app/Models/Transaction.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'transaction_date', 'transaction_type', 'description', 'reference',
    'source', 'status', 'category_id', 'parent_transaction_id', 'notes',
])]
class Transaction extends Model
{
    use HasFactory;

    public const TYPES_ELIGIBLE_FOR_ALLOCATION = ['EXPENSE', 'TRANSFER'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    public function isExpense(): bool
    {
        return $this->transaction_type === 'EXPENSE';
    }

    public function isEligibleForAllocation(): bool
    {
        return in_array($this->transaction_type, self::TYPES_ELIGIBLE_FOR_ALLOCATION, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function childTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }
}
```


---

## FILE: app/Models/User.php

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }

    public function recurringPaymentTemplates(): HasMany
    {
        return $this->hasMany(RecurringPaymentTemplate::class);
    }

    public function paymentObligations(): HasMany
    {
        return $this->hasMany(PaymentObligation::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }

    public function statementImports(): HasMany
    {
        return $this->hasMany(StatementImport::class);
    }

    public function statementTransactions(): HasMany
    {
        return $this->hasMany(StatementTransaction::class);
    }

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function accountReconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
```


---

## FILE: app/Domain/Money.php

```php
<?php

namespace App\Domain;

/**
 * Exact decimal arithmetic for authoritative financial calculations.
 *
 * PHP floats are forbidden for financial calculations (BR-003). All amounts
 * are handled as numeric strings using bcmath, matching the DECIMAL(15,2)
 * database columns.
 */
final class Money
{
    public const SCALE = 2;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::compare($a, '0') > 0;
    }

    public static function isZero(string $a): bool
    {
        return self::compare($a, '0') === 0;
    }

    public static function isGreaterThan(string $a, string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function isGreaterThanOrEqual(string $a, string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }
}
```


---

## FILE: app/Domain/Exceptions/AllocationException.php

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an obligation allocation would violate eligibility, amount,
 * or aggregate-limit rules.
 */
class AllocationException extends RuntimeException {}
```


---

## FILE: app/Domain/Exceptions/InvalidTransactionException.php

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a transaction or its ledger entries would violate the
 * approved V1 ledger movement patterns.
 */
class InvalidTransactionException extends RuntimeException {}
```


---

## FILE: app/Domain/Exceptions/OwnershipViolationException.php

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a request attempts to reference another user's financial data.
 */
class OwnershipViolationException extends RuntimeException {}
```


---

## FILE: app/Domain/Services/AccountBalanceService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Money;
use App\Models\Account;
use App\Models\LedgerEntry;

/**
 * Derives an account's authoritative balance from its opening balance plus
 * posted ledger activity. A cached balance may exist for performance but is
 * never an independent source of truth (00 section 2.2, BR-009).
 */
class AccountBalanceService
{
    public function calculate(Account $account): string
    {
        $inflow = (string) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', 'INFLOW')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $outflow = (string) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', 'OUTFLOW')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $netInflow = Money::sub($inflow, $outflow);

        return $account->isAsset()
            ? Money::add($account->opening_balance, $netInflow)
            : Money::sub($account->opening_balance, $netInflow);
    }
}
```


---

## FILE: app/Domain/Services/ObligationAllocationService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\AllocationException;
use App\Domain\Money;
use App\Models\AuditLog;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bridges actual transactions to planned obligations.
 *
 * Every write locks the obligation row (FOR UPDATE) inside a database
 * transaction, validates the aggregate allocation limit, and recalculates
 * obligation status from the allocation totals (09 sections 4, 8, 24).
 */
class ObligationAllocationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function allocate(
        User $user,
        PaymentObligation $obligation,
        Transaction $transaction,
        string $amount,
    ): ObligationAllocation {
        $this->ownership->assertPaymentObligationOwnership($obligation, $user->id);
        $this->ownership->assertTransactionOwnership($transaction, $user->id);

        if (! Money::isPositive($amount)) {
            throw new AllocationException('Allocated amount must be greater than zero.');
        }

        if (! $transaction->isEligibleForAllocation()) {
            throw new AllocationException('Only EXPENSE and TRANSFER transactions may fulfill a payment obligation.');
        }

        return DB::transaction(function () use ($user, $obligation, $transaction, $amount) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (! $locked->isActive()) {
                throw new AllocationException('Cannot allocate against a SKIPPED or CANCELLED obligation.');
            }

            $currentTotal = $this->allocatedTotal($locked->id);
            $newTotal = Money::add($currentTotal, $amount);

            if (Money::isGreaterThan($newTotal, $locked->planned_amount)) {
                throw new AllocationException('Allocation total cannot exceed the obligation\'s planned amount.');
            }

            $allocation = ObligationAllocation::create([
                'user_id' => $user->id,
                'payment_obligation_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'allocated_amount' => $amount,
            ]);

            $this->recalculateStatus($locked, $newTotal);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ALLOCATION_CREATED',
                'entity_type' => ObligationAllocation::class,
                'entity_id' => $allocation->id,
                'new_values' => [
                    'payment_obligation_id' => $locked->id,
                    'transaction_id' => $transaction->id,
                    'allocated_amount' => $amount,
                ],
            ]);

            return $allocation;
        });
    }

    public function removeAllocation(User $user, ObligationAllocation $allocation): void
    {
        if ($allocation->user_id !== $user->id) {
            throw new AllocationException('Allocation does not belong to the authenticated user.');
        }

        DB::transaction(function () use ($user, $allocation) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($allocation->payment_obligation_id);

            $removed = $allocation->allocated_amount;
            $allocation->delete();

            $remainingTotal = $this->allocatedTotal($locked->id);
            $this->recalculateStatus($locked, $remainingTotal);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ALLOCATION_REMOVED',
                'entity_type' => ObligationAllocation::class,
                'entity_id' => $allocation->id,
                'old_values' => [
                    'payment_obligation_id' => $locked->id,
                    'allocated_amount' => $removed,
                ],
            ]);
        });
    }

    public function skip(User $user, PaymentObligation $obligation): void
    {
        $this->transitionToTerminalState($user, $obligation, 'SKIPPED');
    }

    public function cancel(User $user, PaymentObligation $obligation): void
    {
        $this->transitionToTerminalState($user, $obligation, 'CANCELLED');
    }

    private function transitionToTerminalState(User $user, PaymentObligation $obligation, string $status): void
    {
        $this->ownership->assertPaymentObligationOwnership($obligation, $user->id);

        DB::transaction(function () use ($user, $obligation, $status) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            $total = $this->allocatedTotal($locked->id);

            if (Money::isPositive($total)) {
                throw new AllocationException("Cannot mark an obligation as {$status} while it has active allocations.");
            }

            $previousStatus = $locked->status;
            $locked->update(['status' => $status]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'OBLIGATION_STATUS_CHANGED',
                'entity_type' => PaymentObligation::class,
                'entity_id' => $locked->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $status],
            ]);
        });
    }

    /**
     * Must be called from within the locking transaction so the total
     * reflects the row locked by SELECT ... FOR UPDATE.
     */
    private function allocatedTotal(int $obligationId): string
    {
        $total = DB::table('obligation_allocations')
            ->where('payment_obligation_id', $obligationId)
            ->selectRaw('COALESCE(SUM(allocated_amount), 0) as total')
            ->value('total');

        return (string) $total;
    }

    private function recalculateStatus(PaymentObligation $obligation, string $allocatedTotal): void
    {
        if (! $obligation->isActive()) {
            return;
        }

        $status = match (true) {
            Money::isZero($allocatedTotal) => 'PENDING',
            Money::isGreaterThanOrEqual($allocatedTotal, $obligation->planned_amount) => 'PAID',
            default => 'PARTIALLY_PAID',
        };

        if ($status !== $obligation->status) {
            $previousStatus = $obligation->status;
            $obligation->update(['status' => $status]);

            AuditLog::create([
                'user_id' => $obligation->user_id,
                'action' => 'OBLIGATION_STATUS_CHANGED',
                'entity_type' => PaymentObligation::class,
                'entity_id' => $obligation->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $status],
            ]);
        }
    }
}
```


---

## FILE: app/Domain/Services/OwnershipGuard.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\Transaction;

/**
 * Centralized tenant/ownership enforcement.
 *
 * A valid numeric ID alone is never sufficient authorization. Categories are
 * the only intentional exception: user_id IS NULL represents a system
 * category available to every user.
 */
class OwnershipGuard
{
    public function assertAccountOwnership(Account $account, int $userId): void
    {
        if ($account->user_id !== $userId) {
            throw new OwnershipViolationException('Account does not belong to the authenticated user.');
        }
    }

    public function assertCategoryOwnership(?Category $category, int $userId): void
    {
        if ($category === null) {
            return;
        }

        if (! $category->ownedBy($userId)) {
            throw new OwnershipViolationException('Category is neither a system category nor owned by the authenticated user.');
        }
    }

    public function assertTransactionOwnership(Transaction $transaction, int $userId): void
    {
        if ($transaction->user_id !== $userId) {
            throw new OwnershipViolationException('Transaction does not belong to the authenticated user.');
        }
    }

    public function assertPaymentObligationOwnership(PaymentObligation $obligation, int $userId): void
    {
        if ($obligation->user_id !== $userId) {
            throw new OwnershipViolationException('Payment obligation does not belong to the authenticated user.');
        }
    }

    public function assertRecurringTemplateOwnership(RecurringPaymentTemplate $template, int $userId): void
    {
        if ($template->user_id !== $userId) {
            throw new OwnershipViolationException('Recurring payment template does not belong to the authenticated user.');
        }
    }
}
```


---

## FILE: app/Domain/Services/PaymentObligationService.php

```php
<?php

namespace App\Domain\Services;

use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use DateTimeInterface;

/**
 * Creates Payment Obligations under the approved identity rules.
 *
 * An obligation is strictly Recurring (template + occurrence_key) or
 * One-time (idempotency_key). Generation is idempotent: creating the same
 * occurrence/idempotency key twice never produces a duplicate row (BR-018,
 * 09 section 3 "Identity invariant").
 */
class PaymentObligationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function createRecurringOccurrence(
        User $user,
        RecurringPaymentTemplate $template,
        string $occurrenceKey,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        ?Category $category = null,
    ): PaymentObligation {
        $this->ownership->assertRecurringTemplateOwnership($template, $user->id);

        $resolvedCategory = $category ?? $template->category;
        $this->ownership->assertCategoryOwnership($resolvedCategory, $user->id);

        return PaymentObligation::query()->firstOrCreate(
            [
                'recurring_payment_template_id' => $template->id,
                'occurrence_key' => $occurrenceKey,
            ],
            [
                'user_id' => $user->id,
                'idempotency_key' => null,
                'category_id' => $resolvedCategory->id,
                'planned_account_id' => $template->default_account_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $template->is_mandatory,
            ],
        );
    }

    public function createOneTime(
        User $user,
        string $idempotencyKey,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        bool $isMandatory = true,
        ?int $plannedAccountId = null,
    ): PaymentObligation {
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return PaymentObligation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $user->id,
                'recurring_payment_template_id' => null,
                'occurrence_key' => null,
                'category_id' => $category->id,
                'planned_account_id' => $plannedAccountId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $isMandatory,
            ],
        );
    }
}
```


---

## FILE: app/Domain/Services/RefundService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates REFUND transactions: exactly one INFLOW entry linked to a parent
 * transaction that must be an EXPENSE (BR-014, 09 section 21).
 */
class RefundService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function refund(
        User $user,
        Transaction $parent,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?string $reference = null,
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertTransactionOwnership($parent, $user->id);
        $this->ownership->assertAccountOwnership($account, $user->id);

        if ($parent->transaction_type !== 'EXPENSE') {
            throw new InvalidTransactionException('Refund parent transaction must be an EXPENSE.');
        }

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $parent, $account, $amount, $transactionDate, $description, $reference, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'REFUND',
                'description' => $description,
                'reference' => $reference,
                'source' => 'MANUAL',
                'status' => 'POSTED',
                'category_id' => $parent->category_id,
                'parent_transaction_id' => $parent->id,
                'notes' => $notes,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'direction' => 'INFLOW',
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
```


---

## FILE: app/Domain/Services/ReversalService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates REVERSAL transactions: an exact mirror of the parent transaction's
 * complete ledger structure, same accounts and amounts, every direction
 * inverted (BR-015, 09 section 21).
 */
class ReversalService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function reverse(
        User $user,
        Transaction $parent,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?string $reference = null,
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertTransactionOwnership($parent, $user->id);

        return DB::transaction(function () use (
            $user, $parent, $transactionDate, $description, $reference, $notes,
        ) {
            $parentEntries = $parent->ledgerEntries()->get();

            if ($parentEntries->isEmpty()) {
                throw new InvalidTransactionException('Parent transaction has no ledger entries to reverse.');
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'REVERSAL',
                'description' => $description,
                'reference' => $reference,
                'source' => 'MANUAL',
                'status' => 'POSTED',
                'category_id' => $parent->category_id,
                'parent_transaction_id' => $parent->id,
                'notes' => $notes,
            ]);

            foreach ($parentEntries as $entry) {
                LedgerEntry::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry->account_id,
                    'direction' => $entry->direction === 'INFLOW' ? 'OUTFLOW' : 'INFLOW',
                    'amount' => $entry->amount,
                ]);
            }

            return $transaction->fresh('ledgerEntries');
        });
    }
}
```


---

## FILE: app/Domain/Services/TransactionService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates single-ledger-entry actual transactions: EXPENSE, INCOME, ADJUSTMENT.
 *
 * A Transaction and its Ledger Entries are always created atomically, so a
 * transaction never becomes visible without its required ledger footprint.
 */
class TransactionService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function recordExpense(
        User $user,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        return $this->createSingleEntryTransaction(
            $user, $account, 'EXPENSE', 'OUTFLOW', $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        );
    }

    public function recordIncome(
        User $user,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        return $this->createSingleEntryTransaction(
            $user, $account, 'INCOME', 'INFLOW', $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        );
    }

    /**
     * Adjustments require an explicit reason for auditability (BR-016).
     */
    public function recordAdjustment(
        User $user,
        Account $account,
        string $direction,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        string $reason,
        ?Category $category = null,
        ?string $reference = null,
    ): Transaction {
        if (! in_array($direction, ['INFLOW', 'OUTFLOW'], true)) {
            throw new InvalidTransactionException('Adjustment direction must be INFLOW or OUTFLOW.');
        }

        $transaction = $this->createSingleEntryTransaction(
            $user, $account, 'ADJUSTMENT', $direction, $amount, $transactionDate,
            $description, $category, $reference, 'ADJUSTMENT', $reason,
        );

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'ADJUSTMENT_CREATED',
            'entity_type' => Transaction::class,
            'entity_id' => $transaction->id,
            'new_values' => [
                'account_id' => $account->id,
                'direction' => $direction,
                'amount' => $amount,
            ],
            'metadata' => ['reason' => $reason],
        ]);

        return $transaction;
    }

    private function createSingleEntryTransaction(
        User $user,
        Account $account,
        string $transactionType,
        string $direction,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category,
        ?string $reference,
        string $source,
        ?string $notes,
    ): Transaction {
        $this->ownership->assertAccountOwnership($account, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $account, $transactionType, $direction, $amount,
            $transactionDate, $description, $category, $reference, $source, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => $transactionType,
                'description' => $description,
                'reference' => $reference,
                'source' => $source,
                'status' => 'POSTED',
                'category_id' => $category?->id,
                'notes' => $notes,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'direction' => $direction,
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
```


---

## FILE: app/Domain/Services/TransferService.php

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates TRANSFER transactions: exactly one OUTFLOW and one INFLOW entry of
 * the same amount against two distinct user-owned accounts. Transfers are
 * never income or expense (BR-013).
 */
class TransferService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function transfer(
        User $user,
        Account $fromAccount,
        Account $toAccount,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertAccountOwnership($fromAccount, $user->id);
        $this->ownership->assertAccountOwnership($toAccount, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        if ($fromAccount->id === $toAccount->id) {
            throw new InvalidTransactionException('A transfer requires two distinct accounts.');
        }

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Transfer amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $fromAccount, $toAccount, $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'TRANSFER',
                'description' => $description,
                'reference' => $reference,
                'source' => $source,
                'status' => 'POSTED',
                'category_id' => $category?->id,
                'notes' => $notes,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $fromAccount->id,
                'direction' => 'OUTFLOW',
                'amount' => $amount,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $toAccount->id,
                'direction' => 'INFLOW',
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
```


---

## FILE: composer.json

```json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.8",
        "laravel/tinker": "^3.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.5",
        "laravel/pao": "^1.0.6",
        "laravel/pint": "^1.27",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^12.5.12"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install --ignore-scripts",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi @no_additional_args",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```


---

## FILE: phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="3306"/>
        <env name="DB_DATABASE" value="personal_budget_testing"/>
        <env name="DB_USERNAME" value="root"/>
        <env name="DB_PASSWORD" value=""/>
        <env name="DB_URL" value=""/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
```

