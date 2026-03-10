# Blueprint Modul Accounting / General Ledger

## 1) Core Setup
- Company / Entity
- Branch / Business Unit
- Fiscal Year & Accounting Period
- Currency & Exchange Rate
- Chart of Accounts
- Accounting Dimensions
- Numbering Series
- Approval Matrix
- Report Structure Mapping

## 2) General Ledger
- Opening Balance
- Manual Journal
- Recurring Journal
- Adjustment Journal
- Reversing Journal
- Auto Journal from integrations
- Ledger Posting
- Trial Balance
- General Ledger Inquiry
- Journal Audit Trail

## 3) Period End & Control
- Period Open / Soft Close / Hard Close
- Closing Checklist
- Reconciliation status
- FX Revaluation
- Accrual & Deferral
- Depreciation posting
- Year-end closing
- Retained earnings roll forward

## 4) Financial Reporting
- Statement of Financial Position
- Profit or Loss
- OCI (optional)
- Changes in Equity
- Cashflow Statement
- Trial Balance
- General Ledger
- Notes support schedule
- Comparative reporting
- Segment/cost center reporting

## 5) Integration Hub
- CRM → AR, revenue
- Cash Management → bank/cash movement
- Procurement → AP, accrual, prepaid, inventory receipt reference
- Payroll → salary expense, payable, tax payable
- Inventory → stock value, COGS, adjustment
- Fixed Asset → capitalization, depreciation
- Tax → VAT/WHT reference postings

## 6) Governance & Security
- Roles & permissions
- Approval workflow
- Period lock
- Posting lock
- Audit log
- Integration log
- Error handling & reprocess

## ERD Konseptual (Tingkat Tinggi)

```text
companies
 ├── branches
 ├── fiscal_years
 │    └── accounting_periods
 ├── chart_of_accounts
 │    └── coa_mappings
 ├── dimensions
 │    └── dimension_values
 ├── report_templates
 │    └── report_template_lines
 ├── journal_entries
 │    ├── journal_lines
 │    ├── journal_approvals
 │    ├── journal_attachments
 │    └── journal_reversals
 ├── recurring_journals
 ├── accrual_schedules
 ├── prepayment_schedules
 ├── fixed_assets
 │    └── depreciation_entries
 ├── bank_accounts
 │    └── bank_reconciliations
 ├── posting_rules
 │    └── posting_rule_lines
 ├── integration_events
 │    └── integration_event_logs
 ├── period_closings
 ├── ledger_balances_monthly
 └── audit_logs
```

## Struktur tabel lengkap
Skema tabel lengkap telah diimplementasikan pada migration:

- `database/migrations/2026_03_10_000000_create_accounting_general_ledger_schema.php`

Mencakup domain berikut:
- Master perusahaan, periode, mata uang
- Chart of Accounts + mapping
- Dimensi
- Jurnal + approval + attachment + reversal
- Posting rules + inbox integration event
- Closing + lock + monthly balance cache
- Accrual/prepayment/fixed asset/depreciation
- Bank statement + reconciliation
- Reporting template + cashflow tags
- Approval workflow + audit logs
