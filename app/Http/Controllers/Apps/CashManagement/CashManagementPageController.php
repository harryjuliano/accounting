<?php

namespace App\Http\Controllers\Apps\CashManagement;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\CashManagementAccount;
use App\Models\CashTransaction;
use App\Models\IntegrationEvent;
use App\Models\PettyCashBox;
use App\Models\PettyCashTransaction;
use App\Models\Reimbursement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CashManagementPageController extends Controller
{
    use InteractsWithCompanyScope;

    private const PAGES = [
        'dashboard' => ['title' => 'Cash Dashboard', 'group' => 'Dashboard', 'description' => 'Ringkasan posisi kas, bank, advance, reimbursement, dan event integrasi cash management.'],
        'cash-receipts' => ['title' => 'Cash Receipt', 'group' => 'Cash & Bank', 'description' => 'Penerimaan kas/bank dari customer, bank, employee, atau pihak lain.'],
        'cash-payments' => ['title' => 'Cash Payment', 'group' => 'Cash & Bank', 'description' => 'Pembayaran kas/bank ke vendor, employee, bank, atau pihak lain.'],
        'bank-transfers' => ['title' => 'Bank Transfer', 'group' => 'Cash & Bank', 'description' => 'Transfer internal antar cash/bank account termasuk biaya bank.'],
        'accounts' => ['title' => 'Cash & Bank Accounts', 'group' => 'Cash & Bank', 'description' => 'Master rekening kas, bank, petty cash, e-wallet, dan clearing yang terhubung ke COA.'],
        'petty-cash' => ['title' => 'Petty Cash', 'group' => 'Petty Cash', 'description' => 'Petty cash box, expense entry, replenishment, dan opname.'],
        'cash-advances' => ['title' => 'Cash Advance', 'group' => 'Cash Advance', 'description' => 'Advance request, disbursement, settlement, aging, dan outstanding employee advance.'],
        'reimbursements' => ['title' => 'Reimbursement', 'group' => 'Reimbursement', 'description' => 'Claim, approval, dan payment reimbursement karyawan.'],
        'bank-reconciliations' => ['title' => 'Bank Reconciliation', 'group' => 'Bank Reconciliation', 'description' => 'Import statement, matching, exception, dan adjustment rekonsiliasi bank.'],
        'reports' => ['title' => 'Cash Reports', 'group' => 'Reports', 'description' => 'Cash book, bank book, petty cash, advance aging, reimbursement, dan reconciliation report.'],
        'setup' => ['title' => 'Cash Setup', 'group' => 'Setup', 'description' => 'Payment method, approval matrix, posting preset, dan bank statement mapping.'],
    ];

    public function __invoke(Request $request, string $page = 'dashboard')
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        $companyScope = fn (Builder $query) => $query->when(
            $this->isCompanyAdmin(),
            fn (Builder $builder) => $builder->where('company_id', $request->user()->company_id)
        );

        $transactionScope = CashTransaction::query()->tap($companyScope);
        $accountScope = CashManagementAccount::query()->tap($companyScope);
        $pettyCashTransactionScope = PettyCashTransaction::query()->tap($companyScope);
        $advanceScope = CashAdvance::query()->tap($companyScope);
        $reimbursementScope = Reimbursement::query()->tap($companyScope);

        $recentTransactions = (clone $transactionScope)
            ->with(['cashAccount:id,account_code,account_name', 'targetCashAccount:id,account_code,account_name'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CashTransaction $transaction) => [
                'document_no' => $transaction->document_no,
                'transaction_type' => $transaction->transaction_type,
                'direction' => $transaction->direction,
                'account' => $transaction->cashAccount?->account_name,
                'target_account' => $transaction->targetCashAccount?->account_name,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'journal_entry_id' => $transaction->journal_entry_id,
            ]);

        $integrationEvents = IntegrationEvent::query()
            ->when($this->isCompanyAdmin(), fn (Builder $builder) => $builder->where('company_id', $request->user()->company_id))
            ->whereIn('source_module', ['cash_management', 'petty_cash', 'cash_advance', 'reimbursement', 'bank_reconciliation'])
            ->selectRaw('processing_status, COUNT(*) as total')
            ->groupBy('processing_status')
            ->pluck('total', 'processing_status');

        return inertia('Apps/CashManagement/Index', [
            'page' => array_merge(['key' => $page], self::PAGES[$page]),
            'modules' => $this->modules(),
            'kpis' => [
                'active_cash_accounts' => (int) (clone $accountScope)->where('is_active', true)->count(),
                'cash_balance_cache' => (float) (clone $accountScope)->where('is_active', true)->sum('current_balance_cache'),
                'cash_in' => (float) (clone $transactionScope)->where('direction', 'in')->sum('amount'),
                'cash_out' => (float) (clone $transactionScope)->where('direction', 'out')->sum('amount'),
                'pending_transactions' => (int) (clone $transactionScope)->whereIn('status', ['draft', 'submitted', 'verified', 'approved', 'paid'])->count(),
                'unposted_transactions' => (int) (clone $transactionScope)->whereNotIn('status', ['posted', 'reconciled', 'cancelled'])->count(),
                'petty_cash_boxes' => (int) PettyCashBox::query()->tap($companyScope)->where('is_active', true)->count(),
                'petty_cash_expenses' => (float) (clone $pettyCashTransactionScope)->where('transaction_type', 'expense')->sum('amount'),
                'outstanding_advances' => (float) (clone $advanceScope)->sum('amount_disbursed') - (float) (clone $advanceScope)->sum('amount_settled') - (float) (clone $advanceScope)->sum('amount_returned'),
                'pending_reimbursements' => (float) (clone $reimbursementScope)->whereIn('status', ['submitted', 'verified', 'approved'])->sum('total_amount'),
            ],
            'statusSummary' => (clone $transactionScope)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'integrationSummary' => [
                'received' => (int) ($integrationEvents->get('received', 0)),
                'validated' => (int) ($integrationEvents->get('validated', 0)),
                'processed' => (int) ($integrationEvents->get('processed', 0)),
                'failed' => (int) ($integrationEvents->get('failed', 0)),
            ],
            'recentTransactions' => $recentTransactions,
        ]);
    }

    private function modules(): array
    {
        return [
            ['title' => 'Cash Receipt', 'route' => route('apps.cash-management.page', 'cash-receipts'), 'event' => 'cash.receipt.posted'],
            ['title' => 'Cash Payment', 'route' => route('apps.cash-management.cash-payments.index'), 'event' => 'cash.payment.posted'],
            ['title' => 'Bank Transfer', 'route' => route('apps.cash-management.page', 'bank-transfers'), 'event' => 'cash.transfer.posted'],
            ['title' => 'Petty Cash', 'route' => route('apps.cash-management.page', 'petty-cash'), 'event' => 'petty_cash.expense.posted'],
            ['title' => 'Cash Advance', 'route' => route('apps.cash-management.page', 'cash-advances'), 'event' => 'cash_advance.disbursement.posted'],
            ['title' => 'Reimbursement', 'route' => route('apps.cash-management.page', 'reimbursements'), 'event' => 'reimbursement.payment.posted'],
            ['title' => 'Bank Reconciliation', 'route' => route('apps.cash-management.page', 'bank-reconciliations'), 'event' => 'bank_reconciliation.adjustment.posted'],
        ];
    }
}
