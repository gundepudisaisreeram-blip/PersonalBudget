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
