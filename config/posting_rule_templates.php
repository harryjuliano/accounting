<?php

return [

    /*
    |--------------------------------------------------------------------------
    | INVENTORY RECEIPT
    |--------------------------------------------------------------------------
    */
    'inventory_receipt' => [
        'lines' => [
            [
                'line_no' => 1,
                'side' => 'debit',
                'mapping_key' => 'inventory.receipt.debit.asset',
                'amount_source' => 'payload_total',
                'description' => 'Inventory receipt asset'
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'mapping_key' => 'inventory.receipt.credit.grni',
                'amount_source' => 'payload_total',
                'description' => 'GRNI'
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
