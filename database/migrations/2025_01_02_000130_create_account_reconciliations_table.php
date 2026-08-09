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
