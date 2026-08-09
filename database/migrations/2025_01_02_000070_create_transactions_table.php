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
