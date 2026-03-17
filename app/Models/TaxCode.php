<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'rate',
        'tax_type',
        'is_inclusive',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
