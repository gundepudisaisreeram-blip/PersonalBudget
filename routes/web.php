<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
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
});
