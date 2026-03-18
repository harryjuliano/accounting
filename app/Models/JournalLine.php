<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'account_id',
        'description',
        'debit',
        'credit',
        'base_currency_debit',
        'base_currency_credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'base_currency_debit' => 'decimal:2',
        'base_currency_credit' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
