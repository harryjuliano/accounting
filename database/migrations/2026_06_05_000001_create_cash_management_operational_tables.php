<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_management_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('account_code');
            $table->string('account_name');
            $table->enum('account_type', ['bank', 'cash', 'petty_cash', 'e_wallet', 'clearing']);
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency_code', 3);
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->decimal('current_balance_cache', 20, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['company_id', 'account_code']);
            $table->index(['company_id', 'account_type', 'is_active'], 'cash_mgmt_accounts_company_type_active_idx');
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('document_no');
            $table->enum('transaction_type', ['receipt', 'payment', 'transfer', 'petty_cash_funding', 'petty_cash_replenishment', 'advance_disbursement', 'advance_settlement', 'reimbursement_payment']);
            $table->enum('direction', ['in', 'out', 'transfer']);
            $table->foreignId('cash_management_account_id')->nullable()->constrained('cash_management_accounts')->nullOnDelete();
            $table->foreignId('target_cash_management_account_id')->nullable()->constrained('cash_management_accounts')->nullOnDelete();
            $table->enum('counterparty_type', ['customer', 'vendor', 'employee', 'bank', 'other'])->nullable();
            $table->string('counterparty_code')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('employee_name')->nullable();
            $table->date('transaction_date');
            $table->date('posting_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('bank_charge', 20, 2)->default(0);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'paid', 'posted', 'reconciled', 'cancelled'])->default('draft');
            $table->string('payment_method')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();
            $table->text('narration')->nullable();
            $table->string('attachment_url')->nullable();
            $table->foreignId('integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->enum('reconciliation_status', ['unreconciled', 'matched', 'reconciled', 'exception'])->default('unreconciled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['company_id', 'document_no']);
            $table->index(['company_id', 'transaction_type', 'status'], 'cash_transactions_type_status_idx');
            $table->index(['company_id', 'transaction_date'], 'cash_transactions_company_date_idx');
        });

        Schema::create('petty_cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('box_code');
            $table->string('box_name');
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('custodian_name')->nullable();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->decimal('imprest_limit', 20, 2)->default(0);
            $table->decimal('current_balance_cache', 20, 2)->default(0);
            $table->foreignId('cash_management_account_id')->nullable()->constrained('cash_management_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'box_code']);
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('document_no');
            $table->foreignId('petty_cash_box_id')->constrained('petty_cash_boxes')->cascadeOnDelete();
            $table->enum('transaction_type', ['funding', 'expense', 'replenishment', 'opname', 'adjustment']);
            $table->date('transaction_date');
            $table->date('posting_date')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description');
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('balance_after_cache', 20, 2)->nullable();
            $table->string('receipt_no')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('attachment_url')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_no']);
            $table->index(['company_id', 'transaction_type', 'status'], 'petty_cash_transactions_type_status_idx');
        });

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('document_no');
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('employee_name');
            $table->string('employee_department')->nullable();
            $table->text('purpose');
            $table->enum('advance_type', ['travel', 'operational', 'project', 'event', 'emergency', 'other']);
            $table->date('request_date');
            $table->date('expected_return_date')->nullable();
            $table->decimal('amount_requested', 20, 2)->default(0);
            $table->decimal('amount_disbursed', 20, 2)->default(0);
            $table->decimal('amount_settled', 20, 2)->default(0);
            $table->decimal('amount_returned', 20, 2)->default(0);
            $table->foreignId('cash_management_account_id')->nullable()->constrained('cash_management_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'disbursed', 'partially_settled', 'settled', 'overdue', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('disbursement_integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('settlement_integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('disbursement_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('settlement_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_no']);
            $table->index(['company_id', 'status', 'expected_return_date'], 'cash_advances_status_due_idx');
        });

        Schema::create('cash_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('document_no');
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->date('settlement_date');
            $table->date('posting_date')->nullable();
            $table->decimal('total_expense', 20, 2)->default(0);
            $table->decimal('amount_returned', 20, 2)->default(0);
            $table->decimal('amount_additional', 20, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'paid', 'closed'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_no']);
        });

        Schema::create('cash_advance_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('cash_advance_settlements')->cascadeOnDelete();
            $table->string('expense_category');
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description');
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('receipt_no')->nullable();
            $table->date('receipt_date')->nullable();
            $table->json('dimension_details_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('document_no');
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('employee_name');
            $table->string('employee_department')->nullable();
            $table->date('claim_date');
            $table->date('posting_date')->nullable();
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->foreignId('cash_management_account_id')->nullable()->constrained('cash_management_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'paid', 'posted', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approval_integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('payment_integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('approval_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_no']);
            $table->index(['company_id', 'status', 'claim_date'], 'reimbursements_status_claim_date_idx');
        });

        Schema::create('reimbursement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reimbursement_id')->constrained('reimbursements')->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('expense_category');
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description');
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('receipt_no')->nullable();
            $table->string('attachment_url')->nullable();
            $table->json('dimension_details_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cash_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_no');
            $table->string('action');
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_by_name')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['document_type', 'document_id'], 'cash_approval_logs_document_idx');
        });

        Schema::create('document_numbering_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('module');
            $table->string('series_type');
            $table->string('prefix');
            $table->string('period_format')->default('Ym');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->enum('reset_period', ['never', 'month', 'year'])->default('month');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'module', 'series_type'], 'document_numbering_unique_scope');
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_statement_lines', 'value_date')) {
                $table->date('value_date')->nullable()->after('transaction_date');
            }
            if (! Schema::hasColumn('bank_statement_lines', 'matched_cash_transaction_id')) {
                $table->foreignId('matched_cash_transaction_id')->nullable()->after('matched_journal_line_id')->constrained('cash_transactions')->nullOnDelete();
            }
            if (! Schema::hasColumn('bank_statement_lines', 'match_confidence')) {
                $table->decimal('match_confidence', 5, 2)->nullable()->after('matched_cash_transaction_id');
            }
            if (! Schema::hasColumn('bank_statement_lines', 'match_notes')) {
                $table->text('match_notes')->nullable()->after('match_confidence');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_lines', 'match_notes')) {
                $table->dropColumn('match_notes');
            }
            if (Schema::hasColumn('bank_statement_lines', 'match_confidence')) {
                $table->dropColumn('match_confidence');
            }
            if (Schema::hasColumn('bank_statement_lines', 'matched_cash_transaction_id')) {
                $table->dropConstrainedForeignId('matched_cash_transaction_id');
            }
            if (Schema::hasColumn('bank_statement_lines', 'value_date')) {
                $table->dropColumn('value_date');
            }
        });

        Schema::dropIfExists('document_numbering_series');
        Schema::dropIfExists('cash_approval_logs');
        Schema::dropIfExists('reimbursement_lines');
        Schema::dropIfExists('reimbursements');
        Schema::dropIfExists('cash_advance_settlement_lines');
        Schema::dropIfExists('cash_advance_settlements');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_boxes');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_management_accounts');
    }
};
