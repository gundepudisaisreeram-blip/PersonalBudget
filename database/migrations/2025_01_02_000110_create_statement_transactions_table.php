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
