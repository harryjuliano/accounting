<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'module_name',
        'event_name',
        'transaction_type',
        'rule_code',
        'rule_name',
        'version',
        'effective_from',
        'effective_to',
        'priority',
        'is_active',
        'description',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PostingRuleLine::class)->orderBy('line_no');
    }
}
