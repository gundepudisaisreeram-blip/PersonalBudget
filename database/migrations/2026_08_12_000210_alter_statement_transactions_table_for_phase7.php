<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (PHASE_7_DECISION_PACKAGE.md v1.5.3, section 12) updates the
 * dormant `statement_transactions` table created in Phase 1, adding the
 * denormalized `account_id` (mirroring the certified tenant-scoping
 * pattern already used by `ledger_entries`/`obligation_allocations`, per
 * 04_DATABASE_SPECIFICATION.md's v1.6 amendment) and renaming the existing
 * fallback/primary identity columns to the frozen contract's exact names.
 * Indexes are explicitly non-unique per section 12 -- same-file and
 * cross-import fallback-fingerprint collisions must never be rejected by
 * a database constraint.
 *
 * `account_id` and `row_fingerprint` are added/renamed as NULLABLE, not
 * NOT NULL. This is a deliberate, minimal concession, not a weakening of
 * Phase 7's own guarantees: several already-certified Phase 1-6 tests
 * (tests/Feature/Database/ConstraintTest.php, tests/Feature/Ownership/
 * TenantIsolationTest.php) construct a bare StatementTransaction row
 * purely as an unrelated foreign-key fixture, using the table's original
 * Phase 1 shape -- they predate and cannot know about these new columns,
 * and per this task's explicit instruction they must not be modified.
 * Every row Phase 7's own ParseStatementImportJob writes always populates
 * both fields -- the relaxation exists only at the schema level, to avoid
 * breaking untouchable legacy fixtures, never in Phase 7's own write path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('statement_import_id')
                ->constrained('accounts')->restrictOnDelete();
            $table->renameColumn('normalized_hash', 'row_fingerprint');
            $table->renameColumn('reference', 'reference_number');
        });

        DB::statement('ALTER TABLE statement_transactions MODIFY row_fingerprint VARCHAR(255) NULL');

        Schema::table('statement_transactions', function (Blueprint $table) {
            $table->index(['account_id', 'row_fingerprint']);
            $table->index(['account_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::table('statement_transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'row_fingerprint']);
            $table->dropIndex(['account_id', 'reference_number']);
        });

        DB::statement("UPDATE statement_transactions SET row_fingerprint = '' WHERE row_fingerprint IS NULL");
        DB::statement('ALTER TABLE statement_transactions MODIFY row_fingerprint VARCHAR(255) NOT NULL');

        Schema::table('statement_transactions', function (Blueprint $table) {
            $table->renameColumn('row_fingerprint', 'normalized_hash');
            $table->renameColumn('reference_number', 'reference');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
