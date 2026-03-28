<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_event_id',
        'failure_stage',
        'error_code',
        'error_message',
        'retry_count',
        'last_retry_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'last_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class, 'integration_event_id');
    }
}
