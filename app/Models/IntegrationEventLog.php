<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEventLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'integration_event_id',
        'log_time',
        'level',
        'message',
        'context_json',
    ];

    protected $casts = [
        'log_time' => 'datetime',
        'context_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class, 'integration_event_id');
    }
}
