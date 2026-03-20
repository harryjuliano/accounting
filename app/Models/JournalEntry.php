<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'accounting_period_id',
        'journal_batch_id',
        'journal_no',
        'journal_type',
        'source_module',
        'source_event',
        'source_document_type',
        'source_document_id',
        'source_document_no',
        'integration_key',
        'entry_date',
        'posting_date',
        'reference_no',
        'description',
        'currency_code',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'status',
        'is_adjustment',
        'is_reversing',
        'reversed_entry_id',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posting_date' => 'date',
        'posted_at' => 'datetime',
        'exchange_rate' => 'decimal:10',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_adjustment' => 'boolean',
        'is_reversing' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
