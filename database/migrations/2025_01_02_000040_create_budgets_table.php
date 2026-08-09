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
