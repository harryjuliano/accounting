<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reimbursement extends Model
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
        'claim_date',
        'posting_date',
        'total_amount',
        'cash_management_account_id',
        'status',
        'approved_by',
        'paid_by',
        'paid_at',
        'approval_integration_event_id',
        'payment_integration_event_id',
        'approval_journal_entry_id',
        'payment_journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'posting_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(ReimbursementLine::class);
    }
}
