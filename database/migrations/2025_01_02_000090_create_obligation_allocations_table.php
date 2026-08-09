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
