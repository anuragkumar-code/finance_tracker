<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CreditCardPaymentController;
use App\Http\Controllers\CreditCardStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UpcomingController;
use App\Http\Controllers\Settings\CategoryController;
use App\Http\Controllers\Settings\MerchantController;
use App\Http\Controllers\Settings\MerchantGroupController;
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

/*
 * Reports (spec section 19). Every figure comes from the reporting services and
 * links back into the transaction list filtered the same way, so any number can
 * be opened up and checked.
 */
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/trends', [ReportController::class, 'trends'])->name('reports.trends');
Route::get('/reports/net-worth', [ReportController::class, 'netWorth'])->name('reports.net-worth');
Route::get('/reports/credit-cards', [ReportController::class, 'creditCards'])->name('reports.credit-cards');

/*
 * Budgets (spec section 22). Deliberately the last feature built: a target set
 * before you know your own habits is just a number to feel bad about. The app
 * will not SUGGEST an amount until it has months of real spending to base one on.
 */
Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
Route::delete('/budgets/{category}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
Route::get('/budgets/{category}/suggestion', [BudgetController::class, 'suggest'])->name('budgets.suggest');

/*
 * Reconciliation (spec section 18). The app never edits a calculated balance to
 * match the bank; a gap is closed only by a visible adjustment the household
 * confirms, so every balance stays explainable by its ledger.
 */
Route::get('/reconcile', [ReconciliationController::class, 'index'])->name('reconciliations.index');
Route::post('/reconcile', [ReconciliationController::class, 'store'])->name('reconciliations.store');
Route::post('/reconcile/{reconciliation}/adjust', [ReconciliationController::class, 'adjust'])
    ->name('reconciliations.adjust');

// Backup and spreadsheet export (spec section 31) — local files, no third party.
Route::get('/export/transactions.csv', [ExportController::class, 'transactions'])->name('export.transactions');
Route::get('/export/accounts.csv', [ExportController::class, 'accounts'])->name('export.accounts');

// Things owned outside accounts — land, vehicles.
Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

// What is committed, and what that leaves.
Route::get('/upcoming', [UpcomingController::class, 'index'])->name('upcoming.index');

/*
 * Trips & events, and squaring up with friends. A settlement reshapes real
 * ledger entries, so it is created and undone only through these routes and
 * never by editing the entries it touched.
 */
Route::resource('events', EventController::class);
Route::post('/events/{event}/settlements', [EventController::class, 'settle'])->name('events.settle');
Route::delete('/settlements/{settlement}', [EventController::class, 'undoSettlement'])->name('settlements.undo');

Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
Route::post('/friends', [FriendController::class, 'store'])->name('friends.store');
Route::post('/friends/{person}/repayments', [FriendController::class, 'repayment'])->name('friends.repayment');
Route::post('/friends/{person}/paybacks', [FriendController::class, 'payback'])->name('friends.payback');
Route::post('/friends/{person}/write-offs', [FriendController::class, 'writeOff'])->name('friends.write-off');

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

    // The group master behind the merchant picker.
    Route::post('/merchant-groups', [MerchantGroupController::class, 'store'])->name('merchant-groups.store');
    Route::put('/merchant-groups/{group}', [MerchantGroupController::class, 'update'])->name('merchant-groups.update');
    Route::delete('/merchant-groups/{group}', [MerchantGroupController::class, 'destroy'])->name('merchant-groups.destroy');
});
