<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'bank_name',
        'account_name',
        'account_number',
        'currency_code',
        'gl_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }
}
