<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reimbursement_id',
        'expense_date',
        'expense_category',
        'expense_account_id',
        'description',
        'amount',
        'receipt_no',
        'attachment_url',
        'dimension_details_json',
        'created_at',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'dimension_details_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(Reimbursement::class);
    }
}
