<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingRuleLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'posting_rule_id',
        'line_no',
        'line_side',
        'account_source_type',
        'fixed_account_id',
        'mapping_key',
        'amount_source',
        'formula_json',
        'dimension_rule_json',
        'description_template',
    ];

    protected $casts = [
        'formula_json' => 'array',
        'dimension_rule_json' => 'array',
    ];

    public function postingRule(): BelongsTo
    {
        return $this->belongsTo(PostingRule::class);
    }
}
