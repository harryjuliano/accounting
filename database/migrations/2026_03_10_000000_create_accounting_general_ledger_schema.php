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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('base_currency_code', 3);
            $table->string('country_code', 2);
            $table->string('timezone')->default('Asia/Jakarta');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('year_label');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestamps();

            $table->unique(['company_id', 'year_label']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_no');
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'soft_closed', 'hard_closed', 'audit_closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id', 'period_no']);
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('rate_date');
            $table->string('from_currency_code', 3);
            $table->string('to_currency_code', 3);
            $table->decimal('rate', 24, 10);
            $table->enum('rate_type', ['spot', 'month_end', 'average', 'custom'])->default('spot');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->foreign('from_currency_code')->references('code')->on('currencies');
            $table->foreign('to_currency_code')->references('code')->on('currencies');
            $table->unique(['company_id', 'rate_date', 'from_currency_code', 'to_currency_code', 'rate_type'], 'exchange_rates_unique');
        });

        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'cogs', 'expense', 'other_income', 'other_expense']);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('account_group_id')->nullable()->constrained('account_groups')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('alias_name')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('account_type');
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->string('financial_statement_group');
            $table->string('cashflow_group')->nullable();
            $table->boolean('allow_manual_posting')->default(true);
            $table->boolean('allow_reconciliation')->default(false);
            $table->boolean('requires_dimension')->default(false);
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('coa_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mapping_key');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('module_name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'mapping_key', 'module_name']);
        });

        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['branch', 'department', 'cost_center', 'project', 'customer', 'vendor', 'employee', 'custom']);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('dimensions')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dimension_id', 'code']);
        });

        Schema::create('cashflow_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('activity_type', ['operating', 'investing', 'financing']);
            $table->enum('method', ['direct', 'indirect_adjustment'])->default('indirect_adjustment');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('batch_no');
            $table->string('source_module')->nullable();
            $table->enum('batch_type', ['manual', 'integration', 'closing', 'revaluation', 'depreciation']);
            $table->string('status');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'batch_no']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->foreignId('journal_batch_id')->nullable()->constrained('journal_batches')->nullOnDelete();
            $table->string('journal_no');
            $table->enum('journal_type', ['manual', 'auto', 'adjustment', 'reversing', 'opening', 'closing']);
            $table->string('source_module')->nullable();
            $table->string('source_event')->nullable();
            $table->string('source_document_type')->nullable();
            $table->string('source_document_id')->nullable();
            $table->string('source_document_no')->nullable();
            $table->string('integration_key')->nullable();
            $table->date('entry_date');
            $table->date('posting_date');
            $table->string('reference_no')->nullable();
            $table->text('description');
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->decimal('total_debit', 20, 2)->default(0);
            $table->decimal('total_credit', 20, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'])->default('draft');
            $table->boolean('is_adjustment')->default(false);
            $table->boolean('is_reversing')->default(false);
            $table->foreignId('reversed_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['company_id', 'journal_no']);
            $table->index(['company_id', 'status', 'posting_date']);
            $table->index(['source_module', 'source_document_type', 'source_document_id'], 'journal_entries_source_doc_idx');
            $table->unique(['company_id', 'integration_key'], 'journal_entries_company_integration_unique');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->string('original_currency_code', 3)->nullable();
            $table->decimal('original_currency_amount', 20, 2)->nullable();
            $table->decimal('base_currency_debit', 20, 2)->default(0);
            $table->decimal('base_currency_credit', 20, 2)->default(0);
            $table->foreignId('dimension_branch_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_department_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_cost_center_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_project_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_customer_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_vendor_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('dimension_employee_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->unsignedBigInteger('tax_code_id')->nullable();
            $table->foreignId('cashflow_tag_id')->nullable()->constrained('cashflow_tags')->nullOnDelete();
            $table->timestamps();

            $table->foreign('original_currency_code')->references('code')->on('currencies');
            $table->unique(['journal_entry_id', 'line_no']);
        });

        Schema::create('journal_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'step_no']);
        });

        Schema::create('journal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('journal_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('reversal_journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at');
        });

        Schema::create('recurring_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('template_code');
            $table->string('name');
            $table->string('frequency');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('template_payload_json')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'template_code']);
        });

        Schema::create('posting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module_name');
            $table->string('event_name');
            $table->string('transaction_type');
            $table->string('rule_code');
            $table->string('rule_name');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'rule_code', 'version']);
            $table->index(['company_id', 'module_name', 'event_name', 'is_active'], 'posting_rules_lookup');
        });

        Schema::create('posting_rule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_rule_id')->constrained('posting_rules')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->enum('line_side', ['debit', 'credit']);
            $table->enum('account_source_type', ['fixed', 'mapping', 'payload', 'dynamic']);
            $table->foreignId('fixed_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('mapping_key')->nullable();
            $table->enum('amount_source', ['payload_total', 'payload_tax', 'payload_net', 'formula']);
            $table->json('formula_json')->nullable();
            $table->json('dimension_rule_json')->nullable();
            $table->string('description_template')->nullable();
            $table->timestamps();

            $table->unique(['posting_rule_id', 'line_no']);
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source_module');
            $table->string('event_name');
            $table->string('source_document_type')->nullable();
            $table->string('source_document_id')->nullable();
            $table->string('source_document_no')->nullable();
            $table->string('idempotency_key');
            $table->json('payload_json');
            $table->timestamp('event_datetime');
            $table->enum('processing_status', ['received', 'validated', 'processed', 'failed', 'ignored'])->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['source_module', 'event_name', 'processing_status'], 'integration_events_status');
        });

        Schema::create('integration_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_event_id')->constrained('integration_events')->cascadeOnDelete();
            $table->timestamp('log_time');
            $table->string('level', 20);
            $table->text('message');
            $table->json('context_json')->nullable();
        });

        Schema::create('integration_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_event_id')->constrained('integration_events')->cascadeOnDelete();
            $table->string('failure_stage');
            $table->string('error_code')->nullable();
            $table->text('error_message');
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ledger_balances_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->decimal('opening_debit', 20, 2)->default(0);
            $table->decimal('opening_credit', 20, 2)->default(0);
            $table->decimal('movement_debit', 20, 2)->default(0);
            $table->decimal('movement_credit', 20, 2)->default(0);
            $table->decimal('closing_debit', 20, 2)->default(0);
            $table->decimal('closing_credit', 20, 2)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'accounting_period_id', 'account_id'], 'ledger_balances_core_idx');
        });

        Schema::create('trial_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->string('snapshot_name');
            $table->json('snapshot_data_json');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
        });

        Schema::create('period_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->enum('closing_type', ['soft', 'hard', 'year_end']);
            $table->json('checklist_json')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'failed'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->enum('lock_type', ['journal', 'manual', 'integration', 'all']);
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at');
            $table->text('remarks')->nullable();

            $table->unique(['company_id', 'accounting_period_id', 'lock_type'], 'period_locks_unique');
        });

        Schema::create('accrual_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source_reference');
            $table->text('description')->nullable();
            $table->foreignId('account_expense_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('account_accrual_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 20, 2);
            $table->enum('schedule_method', ['monthly', 'daily', 'custom'])->default('monthly');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('accrual_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accrual_schedule_id')->constrained('accrual_schedules')->cascadeOnDelete();
            $table->date('schedule_date');
            $table->decimal('amount', 20, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('prepayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source_reference');
            $table->text('description')->nullable();
            $table->foreignId('prepaid_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 20, 2);
            $table->string('amortization_method');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('prepayment_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepayment_schedule_id')->constrained('prepayment_schedules')->cascadeOnDelete();
            $table->date('amortization_date');
            $table->decimal('amount', 20, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('depreciation_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedInteger('useful_life_months');
            $table->enum('depreciation_method', ['straight_line', 'declining', 'custom'])->default('straight_line');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('fixed_asset_category_id')->constrained('fixed_asset_categories')->restrictOnDelete();
            $table->string('asset_code');
            $table->string('asset_name');
            $table->date('acquisition_date');
            $table->date('capitalization_date')->nullable();
            $table->decimal('acquisition_cost', 20, 2);
            $table->decimal('residual_value', 20, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('dimension_values')->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'disposed', 'fully_depreciated'])->default('draft');
            $table->timestamps();

            $table->unique(['company_id', 'asset_code']);
        });

        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('depreciation_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->date('depreciation_date');
            $table->decimal('depreciation_amount', 20, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'depreciation_period_id'], 'depreciation_entries_unique_period');
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('currency_code', 3);
            $table->foreignId('gl_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['company_id', 'account_number']);
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('opening_balance', 20, 2);
            $table->decimal('closing_balance', 20, 2);
            $table->string('file_reference')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'statement_date']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->decimal('balance', 20, 2)->nullable();
            $table->string('matched_status')->default('unmatched');
            $table->foreignId('matched_journal_line_id')->nullable()->constrained('journal_lines')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->decimal('statement_balance', 20, 2);
            $table->decimal('book_balance', 20, 2);
            $table->decimal('unreconciled_difference', 20, 2);
            $table->enum('status', ['draft', 'reviewed', 'completed'])->default('draft');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'accounting_period_id']);
        });

        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('report_code');
            $table->string('report_name');
            $table->enum('report_type', ['balance_sheet', 'profit_loss', 'equity', 'cashflow', 'custom']);
            $table->enum('structure_type', ['tree', 'formula'])->default('tree');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'report_code']);
        });

        Schema::create('report_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('report_template_lines')->nullOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('line_code');
            $table->string('line_name');
            $table->enum('line_type', ['header', 'account_group', 'account', 'formula', 'subtotal', 'total']);
            $table->json('account_filter_json')->nullable();
            $table->string('formula_expression')->nullable();
            $table->enum('sign_rule', ['normal', 'invert'])->default('normal');
            $table->unsignedTinyInteger('display_level')->default(1);
            $table->timestamps();

            $table->unique(['report_template_id', 'line_code']);
            $table->unique(['report_template_id', 'line_no']);
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('workflow_code');
            $table->string('workflow_name');
            $table->enum('applies_to', ['journal', 'manual_journal', 'closing', 'integration_exception']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'workflow_code']);
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->unsignedBigInteger('role_id');
            $table->decimal('min_amount', 20, 2)->nullable();
            $table->decimal('max_amount', 20, 2)->nullable();
            $table->json('condition_json')->nullable();
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'step_no']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module_name');
            $table->string('record_type');
            $table->string('record_id');
            $table->string('action');
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'module_name', 'record_type']);
            $table->index(['record_type', 'record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('report_template_lines');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
        Schema::dropIfExists('prepayment_schedule_lines');
        Schema::dropIfExists('prepayment_schedules');
        Schema::dropIfExists('accrual_schedule_lines');
        Schema::dropIfExists('accrual_schedules');
        Schema::dropIfExists('period_locks');
        Schema::dropIfExists('period_closings');
        Schema::dropIfExists('trial_balance_snapshots');
        Schema::dropIfExists('ledger_balances_monthly');
        Schema::dropIfExists('integration_failures');
        Schema::dropIfExists('integration_event_logs');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('posting_rule_lines');
        Schema::dropIfExists('posting_rules');
        Schema::dropIfExists('recurring_journals');
        Schema::dropIfExists('journal_reversals');
        Schema::dropIfExists('journal_attachments');
        Schema::dropIfExists('journal_approvals');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_batches');
        Schema::dropIfExists('cashflow_tags');
        Schema::dropIfExists('dimension_values');
        Schema::dropIfExists('dimensions');
        Schema::dropIfExists('coa_mappings');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('account_groups');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
