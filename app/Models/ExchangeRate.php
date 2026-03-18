<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'rate_date',
        'from_currency_code',
        'to_currency_code',
        'rate',
        'rate_type',
        'source',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fromCurrency()
    {
        return $this->belongsTo(Currency::class, 'from_currency_code', 'code');
    }

    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_code', 'code');
    }
}
