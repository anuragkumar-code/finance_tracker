<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\MerchantController;
use App\Http\Controllers\Settings\PersonController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 * The app is private and runs locally, so there is no authentication in V1
 * (spec section 32). Every route here is household-facing.
 */

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Quick entry — the fast path for daily expense recording (spec section 16).
Route::get('/quick-entry', [QuickEntryController::class, 'create'])->name('quick-entry');
Route::post('/quick-entry', [QuickEntryController::class, 'store'])->name('quick-entry.store');
Route::get('/merchants/{merchant}/defaults', [QuickEntryController::class, 'merchantDefaults'])
    ->name('merchants.defaults');

// Transactions
Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
Route::get('/transactions/new', [TransactionController::class, 'create'])->name('transactions.create');
Route::post('/transactions/income', [TransactionController::class, 'storeIncome'])->name('transactions.income.store');
Route::post('/transactions/transfer', [TransactionController::class, 'storeTransfer'])->name('transactions.transfer.store');
Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void'])->name('transactions.void');
Route::post('/transactions/{id}/restore', [TransactionController::class, 'restore'])->name('transactions.restore');

// Accounts and opening balances
Route::post('/accounts/recalculate', [AccountController::class, 'recalculate'])->name('accounts.recalculate');
Route::resource('accounts', AccountController::class)->except(['destroy']);

// Settings
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/people', [PersonController::class, 'index'])->name('people.index');
    Route::post('/people', [PersonController::class, 'store'])->name('people.store');
    Route::put('/people/{person}', [PersonController::class, 'update'])->name('people.update');
    Route::delete('/people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');

    Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
    Route::post('/merchants', [MerchantController::class, 'store'])->name('merchants.store');
    Route::put('/merchants/{merchant}', [MerchantController::class, 'update'])->name('merchants.update');
    Route::delete('/merchants/{merchant}', [MerchantController::class, 'destroy'])->name('merchants.destroy');
});
