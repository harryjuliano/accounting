<?php

return [



    /*
    |--------------------------------------------------------------------------
    | CASH MANAGEMENT PRESETS
    |--------------------------------------------------------------------------
    */
    'cash_receipt_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash.receipt.debit.cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.receipt_total'],
                'description' => 'Cash/bank receipt from Cash Management',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'cash.receipt.credit.clearing',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.receipt_total'],
                'description' => 'Receipt clearing account',
            ],
        ],
    ],

    'cash_payment_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'cash.payment.debit.clearing',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.payment_total'],
                'description' => 'Cash payment clearing/expense/AP side',
            ],
            [
                'line_no' => 2,
                'side' => 'debit',
                'mapping_key' => 'cash.payment.debit.bank_charge',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.bank_charge'],
                'description' => 'Bank charge from cash payment',
            ],
            [
                'line_no' => 3,
                'side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash.payment.credit.cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sum_paths', 'paths' => ['amounts.payment_total', 'amounts.bank_charge']],
                'description' => 'Cash/bank out from Cash Management',
            ],
        ],
    ],

    'cash_transfer_bank_to_bank' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash.transfer.debit.target_cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.transfer_total'],
                'description' => 'Destination cash/bank account',
            ],
            [
                'line_no' => 2,
                'side' => 'debit',
                'mapping_key' => 'cash.transfer.debit.bank_charge',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.bank_charge'],
                'description' => 'Bank charge on internal transfer',
            ],
            [
                'line_no' => 3,
                'side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash.transfer.credit.source_cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sum_paths', 'paths' => ['amounts.transfer_total', 'amounts.bank_charge']],
                'description' => 'Source cash/bank account',
            ],
        ],
    ],

    'petty_cash_expense_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'petty_cash.expense.debit.expense',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.expense_total'],
                'description' => 'Petty cash expense',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'petty_cash.expense.credit.petty_cash',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.expense_total'],
                'description' => 'Petty cash out',
            ],
        ],
    ],

    'cash_advance_disbursement_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'cash_advance.disbursement.debit.employee_advance',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.disbursement_total'],
                'description' => 'Employee cash advance asset',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash_advance.disbursement.credit.cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.disbursement_total'],
                'description' => 'Cash/bank out for employee advance',
            ],
        ],
    ],

    'cash_advance_settlement_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'cash_advance.settlement.debit.expense',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.expense_total'],
                'description' => 'Advance settlement expense',
            ],
            [
                'line_no' => 2,
                'side' => 'debit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'cash_advance.settlement.debit.cash_return',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.amount_returned'],
                'description' => 'Cash returned by employee',
            ],
            [
                'line_no' => 3,
                'side' => 'credit',
                'mapping_key' => 'cash_advance.settlement.credit.employee_advance',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sum_paths', 'paths' => ['amounts.expense_total', 'amounts.amount_returned']],
                'description' => 'Clear employee advance',
            ],
        ],
    ],

    'reimbursement_payment_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'reimbursement.payment.debit.employee_payable',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.payment_total'],
                'description' => 'Employee reimbursement payable/expense',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'reimbursement.payment.credit.cash_bank',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.payment_total'],
                'description' => 'Cash/bank reimbursement payment',
            ],
        ],
    ],

    'bank_reconciliation_adjustment_standard' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'bank_reconciliation.adjustment.debit.expense_or_cash',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.adjustment_total'],
                'description' => 'Bank reconciliation adjustment debit',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'bank_reconciliation.adjustment.credit.expense_or_cash',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.adjustment_total'],
                'description' => 'Bank reconciliation adjustment credit',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SALES INVOICE POSTED (COMBINED SALES + COGS)
    |--------------------------------------------------------------------------
    */
    'sales_invoice_posted' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.ar',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sales_invoice_receivable_total'],
                'description' => 'Accounts receivable for posted sales invoice',
            ],
            [
                'line_no' => 2,
                'side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.discount',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.discount'],
                'description' => 'Sales discount for posted sales invoice',
            ],
            [
                'line_no' => 3,
                'side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.revenue',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.subtotal'],
                'description' => 'Sales revenue for posted sales invoice',
            ],
            [
                'line_no' => 4,
                'side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.vat_output',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sales_invoice_tax_after_discount'],
                'description' => 'VAT output after discount',
            ],
            [
                'line_no' => 5,
                'side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.freight_income',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.shipping_fee'],
                'description' => 'Sales shipping income',
            ],
            [
                'line_no' => 6,
                'side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.cogs',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sales_invoice_cogs_total'],
                'description' => 'COGS from dispatch cost',
            ],
            [
                'line_no' => 7,
                'side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.inventory',
                'amount_source' => 'formula',
                'formula_json' => ['type' => 'sales_invoice_cogs_total'],
                'description' => 'Inventory reduction from dispatch cost',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | INVENTORY RECEIPT
    |--------------------------------------------------------------------------
    */
    'inventory_receipt_purchase' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.receipt.purchase.debit.inventory',
                'amount_source' => 'payload_total',
                'description' => 'Inventory receipt from purchase'
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.receipt.purchase.credit.grni',
                'amount_source' => 'payload_total',
                'description' => 'Purchase receipt GRNI/AP clearing'
            ],
        ]
    ],

    'inventory_receipt_purchase_return' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.receipt.purchase_return.debit.inventory',
                'amount_source' => 'payload_total',
                'description' => 'Inventory receipt from purchase return'
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
                'amount_source' => 'payload_total',
                'description' => 'Purchase return receipt clearing'
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | INVENTORY ISSUE (DYNAMIC PURPOSE)
    |--------------------------------------------------------------------------
    */
    'inventory_issue_dynamic' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'inventory.issue.debit.{issue_purpose}',
                'amount_source' => 'payload_total',
                'description' => 'Dynamic issue based on purpose'
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'inventory.issue.credit.inventory',
                'amount_source' => 'payload_total',
                'description' => 'Inventory reduction'
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | INVENTORY ADJUSTMENT INCREASE
    |--------------------------------------------------------------------------
    */
    'inventory_adjustment_plus' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.adjustment.debit.inventory',
                'amount_source' => 'payload_total',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.adjustment.credit.gain',
                'amount_source' => 'payload_total',
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | INVENTORY ADJUSTMENT DECREASE
    |--------------------------------------------------------------------------
    */
    'inventory_adjustment_minus' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.adjustment.debit.loss',
                'amount_source' => 'payload_total',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.adjustment.credit.inventory',
                'amount_source' => 'payload_total',
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | INVENTORY COGS
    |--------------------------------------------------------------------------
    */
    'inventory_cogs' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.cogs.debit.cogs',
                'amount_source' => 'payload_total',
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.cogs.credit.inventory',
                'amount_source' => 'payload_total',
            ],
        ]
    ],

];
