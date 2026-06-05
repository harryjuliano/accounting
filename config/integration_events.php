<?php

return [

    'sales' => [
        'sales.invoice.posted' => [
            'label' => 'Sales Invoice Posted',
            'transaction_type' => 'sales.invoice.standard',
            'template' => 'sales_invoice_posted',
            'description' => 'Posted sales invoice creates combined sales and COGS journal',
        ],
    ],


    'cash_management' => [
        'cash.receipt.posted' => [
            'label' => 'Cash Receipt Posted',
            'transaction_type' => 'cash.receipt.standard',
            'template' => 'cash_receipt_standard',
            'description' => 'Cash receipt from Cash Management has been posted',
        ],
        'cash.payment.posted' => [
            'label' => 'Cash Payment Posted',
            'transaction_type' => 'cash.payment.standard',
            'template' => 'cash_payment_standard',
            'description' => 'Cash payment from Cash Management has been posted',
        ],
        'cash.transfer.posted' => [
            'label' => 'Cash Transfer Posted',
            'transaction_type' => 'cash.transfer.bank_to_bank',
            'template' => 'cash_transfer_bank_to_bank',
            'description' => 'Internal cash/bank transfer has been posted',
        ],
        'petty_cash.expense.posted' => [
            'label' => 'Petty Cash Expense Posted',
            'transaction_type' => 'petty_cash.expense.standard',
            'template' => 'petty_cash_expense_standard',
            'description' => 'Petty cash expense has been posted',
        ],
        'cash_advance.disbursement.posted' => [
            'label' => 'Cash Advance Disbursement Posted',
            'transaction_type' => 'cash_advance.disbursement.standard',
            'template' => 'cash_advance_disbursement_standard',
            'description' => 'Employee cash advance has been disbursed',
        ],
        'cash_advance.settlement.posted' => [
            'label' => 'Cash Advance Settlement Posted',
            'transaction_type' => 'cash_advance.settlement.standard',
            'template' => 'cash_advance_settlement_standard',
            'description' => 'Employee cash advance settlement has been posted',
        ],
        'reimbursement.payment.posted' => [
            'label' => 'Reimbursement Payment Posted',
            'transaction_type' => 'reimbursement.payment.standard',
            'template' => 'reimbursement_payment_standard',
            'description' => 'Employee reimbursement payment has been posted',
        ],
        'bank_reconciliation.adjustment.posted' => [
            'label' => 'Bank Reconciliation Adjustment Posted',
            'transaction_type' => 'bank_reconciliation.adjustment.standard',
            'template' => 'bank_reconciliation_adjustment_standard',
            'description' => 'Bank reconciliation adjustment has been posted',
        ],
    ],

    'inventory' => [

        /*
        |--------------------------------------------------------------------------
        | INVENTORY RECEIPT (Barang Masuk)
        |--------------------------------------------------------------------------
        */
        'inventory.receipt.posted' => [
            'label' => 'Inventory Receipt Posted',
            'transaction_type' => 'inventory.receipt.purchase',
            'template' => 'inventory_receipt_purchase',
            'description' => 'Goods receipt from purchase has been posted',
            'allowed_values' => [
                'transaction_type' => [
                    'inventory.receipt.purchase',
                    'inventory.receipt.purchase_return',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | INVENTORY ISSUE (Barang Keluar - Dynamic Purpose)
        |--------------------------------------------------------------------------
        */
        'inventory.issue.posted' => [
            'label' => 'Inventory Issue Posted',
            'transaction_type' => 'inventory.issue.posted',
            'template' => 'inventory_issue_dynamic',
            'description' => 'Inventory issued based on purpose',
            'required_payload' => [
                'issue_purpose'
            ],
            'allowed_values' => [
                'issue_purpose' => [
                    'maintenance',
                    'production',
                    'cogs',
                    'scrap'
                ]
            ]
        ],

        /*
        |--------------------------------------------------------------------------
        | INVENTORY ADJUSTMENT INCREASE
        |--------------------------------------------------------------------------
        */
        'inventory.adjustment.increase' => [
            'label' => 'Inventory Adjustment Increase',
            'transaction_type' => 'inventory.adjustment.increase',
            'template' => 'inventory_adjustment_plus',
        ],

        /*
        |--------------------------------------------------------------------------
        | INVENTORY ADJUSTMENT DECREASE
        |--------------------------------------------------------------------------
        */
        'inventory.adjustment.decrease' => [
            'label' => 'Inventory Adjustment Decrease',
            'transaction_type' => 'inventory.adjustment.decrease',
            'template' => 'inventory_adjustment_minus',
        ],

        /*
        |--------------------------------------------------------------------------
        | INVENTORY COGS (Sales)
        |--------------------------------------------------------------------------
        */
        'inventory.cogs.posted' => [
            'label' => 'Inventory COGS Posted',
            'transaction_type' => 'inventory.cogs.posted',
            'template' => 'inventory_cogs',
        ],

    ],

];
