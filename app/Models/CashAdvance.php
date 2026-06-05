<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_no',
        'employee_user_id',
        'employee_code',
        'employee_name',
        'employee_department',
        'purpose',
        'advance_type',
        'request_date',
        'expected_return_date',
        'amount_requested',
        'amount_disbursed',
        'amount_settled',
        'amount_returned',
        'cash_management_account_id',
        'status',
        'approved_by',
        'disbursed_by',
        'settled_by',
        'disbursed_at',
        'settled_at',
        'disbursement_integration_event_id',
        'settlement_integration_event_id',
        'disbursement_journal_entry_id',
        'settlement_journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'expected_return_date' => 'date',
        'amount_requested' => 'decimal:2',
        'amount_disbursed' => 'decimal:2',
        'amount_settled' => 'decimal:2',
        'amount_returned' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function settlements(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlement::class);
    }
}
