<?php

use App\Http\Controllers\Apps\BalanceSheetReportController;
use App\Http\Controllers\Apps\BranchController;
use App\Http\Controllers\Apps\CashManagement\CashManagementPageController;
use App\Http\Controllers\Apps\CashManagement\CashPaymentController;
use App\Http\Controllers\Apps\CompanyController;
use App\Http\Controllers\Apps\ChartOfAccountController;
use App\Http\Controllers\Apps\CurrencyController;
use App\Http\Controllers\Apps\DashboardController;
use App\Http\Controllers\Apps\ExchangeRateController;
use App\Http\Controllers\Apps\GeneralLedgerReportController;
use App\Http\Controllers\Apps\IndirectCashFlowReportController;
use App\Http\Controllers\Apps\IntegrationClientSecretController;
use App\Http\Controllers\Apps\IntegrationEventController;
use App\Http\Controllers\Apps\IntegrationJournalController;
use App\Http\Controllers\Apps\PostingRuleSetupController;
use App\Http\Controllers\Apps\ProfitLossReportController;
use App\Http\Controllers\Apps\TrialBalanceReportController;
use App\Http\Controllers\Apps\DimensionController;
use App\Http\Controllers\Apps\FiscalPeriodController;
use App\Http\Controllers\Apps\ManualJournalController;
use App\Http\Controllers\Apps\OpeningBalanceCrudController;
use App\Http\Controllers\Apps\PermissionController;
use App\Http\Controllers\Apps\RoleController;
use App\Http\Controllers\Apps\TaxCodeController;
use App\Http\Controllers\Apps\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::group(['prefix' => 'apps', 'as' => 'apps.' , 'middleware' => ['auth']], function(){
    // dashboard route
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // permissions route
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    // roles route
    Route::resource('/roles', RoleController::class)->except(['create', 'edit', 'show']);
    // users route
    Route::resource('/users', UserController::class)->except('show');
    // companies route
    Route::resource('/companies', CompanyController::class)->except(['create', 'edit', 'show']);
    // branches route
    Route::resource('/branches', BranchController::class)->except(['create', 'edit', 'show']);
    // fiscal periods route
    Route::resource('/fiscal-periods', FiscalPeriodController::class)->except(['create', 'edit', 'show'])->parameters(['fiscal-periods' => 'fiscal_period']);
    Route::post('/fiscal-periods/{fiscal_period}/accounting-periods/{accounting_period}/toggle-close', [FiscalPeriodController::class, 'toggleMonthlyClose'])->name('fiscal-periods.accounting-periods.toggle-close');
    Route::post('/fiscal-periods/{fiscal_period}/hard-close', [FiscalPeriodController::class, 'hardCloseYear'])->name('fiscal-periods.hard-close');
    // chart of accounts route
    Route::resource('/chart-of-accounts', ChartOfAccountController::class)->except(['create', 'edit', 'show'])->parameters(['chart-of-accounts' => 'chart_of_account']);
    Route::post('/chart-of-accounts/import-master-template', [ChartOfAccountController::class, 'importMasterTemplate'])->name('chart-of-accounts.import-master-template');
    Route::post('/chart-of-accounts/import-default-template', [ChartOfAccountController::class, 'importDefaultTemplate'])->name('chart-of-accounts.import-default-template');
    Route::post('/chart-of-accounts/import-transaction-template', [ChartOfAccountController::class, 'importTransactionTemplate'])->name('chart-of-accounts.import-transaction-template');
    Route::get('/chart-of-accounts/export-master-template', [ChartOfAccountController::class, 'exportMasterTemplate'])->name('chart-of-accounts.export-master-template');
    Route::get('/chart-of-accounts/export-transaction-template', [ChartOfAccountController::class, 'exportTransactionTemplate'])->name('chart-of-accounts.export-transaction-template');
    // dimensions route
    Route::resource('/dimensions', DimensionController::class)->except(['create', 'edit', 'show']);
    // tax codes route
    Route::resource('/tax-codes', TaxCodeController::class)->except(['create', 'edit', 'show'])->parameters(['tax-codes' => 'tax_code']);
    // currencies route
    Route::resource('/currencies', CurrencyController::class)->except(['create', 'edit', 'show']);
    // exchange rates route
    Route::resource('/exchange-rates', ExchangeRateController::class)->except(['index', 'create', 'edit', 'show']);
    // manual journals route
    Route::resource('/manual-journals', ManualJournalController::class)->except(['create', 'edit', 'show'])->parameters(['manual-journals' => 'manual_journal']);
    Route::get('/manual-journals/integration-journal', IntegrationJournalController::class)->name('manual-journals.integration-journal');
    Route::get('/cash-management', [CashManagementPageController::class, '__invoke'])->name('cash-management.index');
    Route::get('/cash-management/cash-payments/{cash_payment}/voucher', [CashPaymentController::class, 'voucher'])->name('cash-management.cash-payments.voucher');
    Route::resource('/cash-management/cash-payments', CashPaymentController::class)->except(['create', 'edit', 'show'])->parameters(['cash-payments' => 'cash_payment'])->names('cash-management.cash-payments');
    Route::get('/cash-management/{page}', CashManagementPageController::class)->name('cash-management.page');
    Route::get('/integration-events', IntegrationEventController::class)->name('integration-events.index');
    Route::post('/integration-events/{integrationEvent}/validate', [IntegrationEventController::class, 'validateEvent'])->name('integration-events.validate');
    Route::post('/integration-events/{integrationEvent}/post', [IntegrationEventController::class, 'postEvent'])->name('integration-events.post');
    Route::post('/manual-journals/bulk-post', [ManualJournalController::class, 'bulkPost'])->name('manual-journals.bulk-post');
    Route::post('/manual-journals/import', [ManualJournalController::class, 'importFromCsv'])->name('manual-journals.import');
    Route::get('/manual-journals/import-template', [ManualJournalController::class, 'downloadImportTemplate'])->name('manual-journals.import-template');
    Route::resource('/opening-balances', OpeningBalanceCrudController::class)->except(['create', 'edit', 'show'])->parameters(['opening-balances' => 'opening_balance']);
    Route::post('/opening-balances/bulk-post', [OpeningBalanceCrudController::class, 'bulkPost'])->name('opening-balances.bulk-post');
    Route::post('/opening-balances/import', [OpeningBalanceCrudController::class, 'importFromCsv'])->name('opening-balances.import');
    Route::get('/opening-balances/import-template', [OpeningBalanceCrudController::class, 'downloadImportTemplate'])->name('opening-balances.import-template');
    Route::get('/reports/general-ledger', GeneralLedgerReportController::class)->name('reports.general-ledger');
    Route::get('/reports/trial-balance', TrialBalanceReportController::class)->name('reports.trial-balance');
    Route::get('/reports/trial-balance/export', [TrialBalanceReportController::class, 'export'])->name('reports.trial-balance.export');
    Route::get('/reports/profit-loss', ProfitLossReportController::class)->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', BalanceSheetReportController::class)->name('reports.balance-sheet');
    Route::get('/reports/indirect-cash-flow', IndirectCashFlowReportController::class)->name('reports.indirect-cash-flow');
    Route::get('/integration/client-secrets', [IntegrationClientSecretController::class, 'index'])->name('integration-client-secrets.index');
    Route::post('/integration/client-secrets', [IntegrationClientSecretController::class, 'store'])->name('integration-client-secrets.store');
    Route::put('/integration/client-secrets/{integrationClientSecret}', [IntegrationClientSecretController::class, 'update'])->name('integration-client-secrets.update');
    Route::delete('/integration/client-secrets/{integrationClientSecret}', [IntegrationClientSecretController::class, 'destroy'])->name('integration-client-secrets.destroy');
    Route::patch('/integration/client-secrets/{integrationClientSecret}/toggle-status', [IntegrationClientSecretController::class, 'toggleStatus'])->name('integration-client-secrets.toggle-status');
    Route::resource('/setup/preset-journals', PostingRuleSetupController::class)
        ->names('preset-journals')
        ->except(['create', 'edit', 'show'])
        ->parameters(['preset-journals' => 'integrationPostingRule']);
    Route::resource('/integration/posting-rules', PostingRuleSetupController::class)
        ->names('integration-posting-rules')
        ->except(['create', 'edit', 'show'])
        ->parameters(['posting-rules' => 'integrationPostingRule']);
});

require __DIR__.'/auth.php';
