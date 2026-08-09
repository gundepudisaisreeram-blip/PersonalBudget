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
