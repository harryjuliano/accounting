<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationOutbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'integration_event_id',
        'journal_entry_id',
        'source_module',
        'destination_system',
        'event_name',
        'idempotency_key',
        'payload_json',
        'status',
        'retry_count',
        'available_at',
        'dispatched_at',
        'error_message',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'available_at' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function integrationEvent(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
