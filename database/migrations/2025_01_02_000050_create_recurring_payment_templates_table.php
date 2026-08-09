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
        Schema::create('recurring_payment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('frequency');
            $table->string('due_rule');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('status', ['ACTIVE', 'CANCELLED'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_payment_templates');
    }
};
