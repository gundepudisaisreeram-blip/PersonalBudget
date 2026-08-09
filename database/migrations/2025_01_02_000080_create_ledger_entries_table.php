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
