<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_group_id',
        'parent_id',
        'code',
        'name',
        'alias_name',
        'level',
        'account_type',
        'normal_balance',
        'financial_statement_group',
        'cashflow_group',
        'allow_manual_posting',
        'allow_reconciliation',
        'requires_dimension',
        'is_control_account',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
