<?php

return [


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
