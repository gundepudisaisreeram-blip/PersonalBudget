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
