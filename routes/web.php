<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CreditCardPaymentController;
use App\Http\Controllers\CreditCardStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\UpcomingController;
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

/*
 * Credit cards. Three separate things live here and must never be conflated:
 * purchases (recorded via Quick Entry like any expense), statements (a grouping
 * of those purchases, creating no new financial event), and bill payments
 * (which clear the liability without adding to spending).
 */
Route::resource('credit-cards', CreditCardController::class)->except(['destroy']);

Route::prefix('credit-cards/{creditCard}')->name('credit-cards.')->group(function () {
    Route::get('/statements/new', [CreditCardStatementController::class, 'create'])->name('statements.create');
    Route::post('/statements', [CreditCardStatementController::class, 'store'])->name('statements.store');
    Route::get('/statements/{statement}', [CreditCardStatementController::class, 'show'])->name('statements.show');
    Route::post('/statements/{statement}/regenerate', [CreditCardStatementController::class, 'regenerate'])
        ->name('statements.regenerate');

    Route::get('/payments/new', [CreditCardPaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [CreditCardPaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/{payment}/void', [CreditCardPaymentController::class, 'void'])->name('payments.void');
});

/*
 * Loans, simplified to EMI and tenure (household decision): no interest rate,
 * no amortisation. The schedule is laid out once and confirmed month by month.
 */
Route::resource('loans', LoanController::class)->except(['destroy']);
Route::post('/loans/{loan}/instalments/{payment}/pay', [LoanController::class, 'payInstalment'])
    ->name('loans.instalments.pay');
Route::post('/loans/{loan}/instalments/{payment}/unpay', [LoanController::class, 'unpayInstalment'])
    ->name('loans.instalments.unpay');

// Recurring commitments — forecasts until someone confirms them.
Route::get('/recurring', [RecurringTransactionController::class, 'index'])->name('recurring.index');
Route::post('/recurring', [RecurringTransactionController::class, 'store'])->name('recurring.store');
Route::put('/recurring/{recurring}', [RecurringTransactionController::class, 'update'])->name('recurring.update');
Route::delete('/recurring/{recurring}', [RecurringTransactionController::class, 'destroy'])->name('recurring.destroy');
Route::post('/recurring/occurrences/{occurrence}/confirm', [RecurringTransactionController::class, 'confirm'])
    ->name('recurring.occurrences.confirm');
Route::post('/recurring/occurrences/{occurrence}/skip', [RecurringTransactionController::class, 'skip'])
    ->name('recurring.occurrences.skip');

// What is committed, and what that leaves.
Route::get('/upcoming', [UpcomingController::class, 'index'])->name('upcoming.index');

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
