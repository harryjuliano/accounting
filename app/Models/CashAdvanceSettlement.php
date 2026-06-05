<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvanceSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_no',
        'cash_advance_id',
        'settlement_date',
        'posting_date',
        'total_expense',
        'amount_returned',
        'amount_additional',
        'status',
        'approved_by',
        'paid_by',
        'integration_event_id',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'posting_date' => 'date',
        'total_expense' => 'decimal:2',
        'amount_returned' => 'decimal:2',
        'amount_additional' => 'decimal:2',
    ];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class, 'cash_advance_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlementLine::class, 'settlement_id');
    }
}
