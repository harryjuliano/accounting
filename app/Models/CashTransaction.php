<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_no',
        'transaction_type',
        'direction',
        'cash_management_account_id',
        'target_cash_management_account_id',
        'counterparty_type',
        'counterparty_code',
        'counterparty_name',
        'employee_code',
        'employee_name',
        'transaction_date',
        'posting_date',
        'due_date',
        'amount',
        'bank_charge',
        'currency_code',
        'exchange_rate',
        'status',
        'payment_method',
        'reference_no',
        'description',
        'narration',
        'attachment_url',
        'integration_event_id',
        'journal_entry_id',
        'reconciliation_status',
        'created_by',
        'approved_by',
        'posted_by',
        'approved_at',
        'posted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'bank_charge' => 'decimal:2',
        'exchange_rate' => 'decimal:10',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashManagementAccount::class, 'cash_management_account_id');
    }

    public function targetCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashManagementAccount::class, 'target_cash_management_account_id');
    }

    public function integrationEvent(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
