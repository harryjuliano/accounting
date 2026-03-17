<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'tax_id',
        'base_currency_code',
        'country_code',
        'timezone',
        'fiscal_year_start_month',
        'is_active',
    ];
}
