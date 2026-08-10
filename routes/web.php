<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ObligationAllocationController;
use App\Http\Controllers\PaymentObligationController;
use App\Http\Controllers\RecurringPaymentTemplateController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::patch('/accounts/{account}/close', [AccountController::class, 'close'])->name('accounts.close');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('/categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');

    Route::get('/transactions/expense', [TransactionController::class, 'createExpense'])->name('transactions.expense.create');
    Route::post('/transactions/expense', [TransactionController::class, 'storeExpense'])->name('transactions.expense.store');

    Route::get('/transactions/income', [TransactionController::class, 'createIncome'])->name('transactions.income.create');
    Route::post('/transactions/income', [TransactionController::class, 'storeIncome'])->name('transactions.income.store');

    Route::get('/transactions/transfer', [TransactionController::class, 'createTransfer'])->name('transactions.transfer.create');
    Route::post('/transactions/transfer', [TransactionController::class, 'storeTransfer'])->name('transactions.transfer.store');

    Route::get('/transactions/refund', [TransactionController::class, 'createRefund'])->name('transactions.refund.create');
    Route::post('/transactions/refund', [TransactionController::class, 'storeRefund'])->name('transactions.refund.store');

    Route::get('/transactions/reversal', [TransactionController::class, 'createReversal'])->name('transactions.reversal.create');
    Route::post('/transactions/reversal', [TransactionController::class, 'storeReversal'])->name('transactions.reversal.store');

    Route::get('/transactions/adjustment', [TransactionController::class, 'createAdjustment'])->name('transactions.adjustment.create');
    Route::post('/transactions/adjustment', [TransactionController::class, 'storeAdjustment'])->name('transactions.adjustment.store');

    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/recurring-templates', [RecurringPaymentTemplateController::class, 'index'])->name('recurring-templates.index');
    Route::get('/recurring-templates/create', [RecurringPaymentTemplateController::class, 'create'])->name('recurring-templates.create');
    Route::post('/recurring-templates', [RecurringPaymentTemplateController::class, 'store'])->name('recurring-templates.store');
    Route::get('/recurring-templates/{recurringPaymentTemplate}', [RecurringPaymentTemplateController::class, 'show'])->name('recurring-templates.show');
    Route::get('/recurring-templates/{recurringPaymentTemplate}/edit', [RecurringPaymentTemplateController::class, 'edit'])->name('recurring-templates.edit');
    Route::put('/recurring-templates/{recurringPaymentTemplate}', [RecurringPaymentTemplateController::class, 'update'])->name('recurring-templates.update');
    Route::patch('/recurring-templates/{recurringPaymentTemplate}/cancel', [RecurringPaymentTemplateController::class, 'cancel'])->name('recurring-templates.cancel');

    Route::get('/obligations', [PaymentObligationController::class, 'index'])->name('obligations.index');
    Route::get('/obligations/create', [PaymentObligationController::class, 'create'])->name('obligations.create');
    Route::post('/obligations', [PaymentObligationController::class, 'store'])->name('obligations.store');
    Route::post('/obligations/generate', [PaymentObligationController::class, 'generate'])->name('obligations.generate');
    Route::get('/obligations/{paymentObligation}', [PaymentObligationController::class, 'show'])->name('obligations.show');
    Route::patch('/obligations/{paymentObligation}/skip', [PaymentObligationController::class, 'skip'])->name('obligations.skip');
    Route::patch('/obligations/{paymentObligation}/cancel', [PaymentObligationController::class, 'cancel'])->name('obligations.cancel');

    Route::get('/obligations/{paymentObligation}/allocations/create', [ObligationAllocationController::class, 'create'])->name('obligations.allocations.create');
    Route::post('/obligations/{paymentObligation}/allocations', [ObligationAllocationController::class, 'store'])->name('obligations.allocations.store');
    Route::delete('/obligations/{paymentObligation}/allocations/{obligationAllocation}', [ObligationAllocationController::class, 'destroy'])->name('obligations.allocations.destroy');

    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::get('/budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit');
    Route::put('/budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
});
