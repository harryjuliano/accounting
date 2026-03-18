<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'attribute_schema_json',
        'is_mandatory',
        'is_active',
    ];

    protected $casts = [
        'attribute_schema_json' => 'array',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function chartOfAccounts()
    {
        return $this->belongsToMany(ChartOfAccount::class, 'chart_of_account_dimension')
            ->withTimestamps();
    }
}
