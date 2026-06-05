<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'document_no',
        'petty_cash_box_id',
        'transaction_type',
        'transaction_date',
        'posting_date',
        'category',
        'expense_account_id',
        'description',
        'amount',
        'balance_after_cache',
        'receipt_no',
        'requested_by',
        'requested_by_name',
        'status',
        'approved_by',
        'posted_by',
        'integration_event_id',
        'journal_entry_id',
        'attachment_url',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after_cache' => 'decimal:2',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(PettyCashBox::class, 'petty_cash_box_id');
    }
}
