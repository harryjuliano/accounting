<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceSettlementLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'settlement_id',
        'expense_category',
        'expense_account_id',
        'description',
        'amount',
        'receipt_no',
        'receipt_date',
        'dimension_details_json',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
        'dimension_details_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceSettlement::class, 'settlement_id');
    }
}
