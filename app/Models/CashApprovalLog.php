<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashApprovalLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'document_type',
        'document_id',
        'document_no',
        'action',
        'action_by',
        'action_by_name',
        'old_status',
        'new_status',
        'notes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
