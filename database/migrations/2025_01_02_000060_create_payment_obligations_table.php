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
        Schema::create('payment_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recurring_payment_template_id')->nullable()
                ->constrained('recurring_payment_templates')->restrictOnDelete();
            $table->string('occurrence_key')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('planned_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->decimal('planned_amount', 15, 2);
            $table->enum('status', ['PENDING', 'PARTIALLY_PAID', 'PAID', 'SKIPPED', 'CANCELLED'])
                ->default('PENDING');
            $table->boolean('is_mandatory')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->unique(['recurring_payment_template_id', 'occurrence_key'], 'payment_obligations_template_occurrence_unique');
            $table->unique('idempotency_key');
            $table->index('user_id');
            $table->index('category_id');
            $table->index('due_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payment_obligations ADD CONSTRAINT chk_payment_obligations_identity CHECK (
                (
                    recurring_payment_template_id IS NOT NULL
                    AND occurrence_key IS NOT NULL
                    AND idempotency_key IS NULL
                )
                OR
                (
                    recurring_payment_template_id IS NULL
                    AND occurrence_key IS NULL
                    AND idempotency_key IS NOT NULL
                )
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_obligations');
    }
};
