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
