<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentNumberingSeries extends Model
{
    use HasFactory;

    protected $table = 'document_numbering_series';

    protected $fillable = [
        'company_id',
        'branch_id',
        'module',
        'series_type',
        'prefix',
        'period_format',
        'last_sequence',
        'reset_period',
        'is_active',
    ];

    protected $casts = [
        'last_sequence' => 'integer',
        'is_active' => 'boolean',
    ];
}
