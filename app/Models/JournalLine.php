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
        'original_currency_code',
        'original_currency_amount',
        'base_currency_debit',
        'base_currency_credit',
        'dimension_details_json',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'original_currency_amount' => 'decimal:2',
        'base_currency_debit' => 'decimal:2',
        'base_currency_credit' => 'decimal:2',
        'dimension_details_json' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
