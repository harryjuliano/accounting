<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'source_module',
        'event_name',
        'source_document_type',
        'source_document_id',
        'source_document_no',
        'idempotency_key',
        'payload_json',
        'event_datetime',
        'processing_status',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'event_datetime' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationEventLog::class)->latest('log_time');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(IntegrationFailure::class);
    }
}
