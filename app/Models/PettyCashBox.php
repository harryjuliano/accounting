<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'box_code',
        'box_name',
        'custodian_user_id',
        'custodian_name',
        'department',
        'location',
        'imprest_limit',
        'current_balance_cache',
        'cash_management_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'imprest_limit' => 'decimal:2',
        'current_balance_cache' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashManagementAccount::class, 'cash_management_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class);
    }
}
