<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\IntegrationEventLog;
use App\Models\IntegrationFailure;

class IntegrationEventLifecycleService
{
    public function log(IntegrationEvent $event, string $level, string $message, array $context = []): void
    {
        IntegrationEventLog::create([
            'integration_event_id' => $event->id,
            'log_time' => now(),
            'level' => $level,
            'message' => $message,
            'context_json' => $context ?: null,
        ]);
    }

    public function recordFailure(IntegrationEvent $event, string $stage, string $errorCode, string $errorMessage): void
    {
        $failure = IntegrationFailure::query()
            ->where('integration_event_id', $event->id)
            ->whereNull('resolved_at')
            ->latest('id')
            ->first();

        if ($failure) {
            $failure->update([
                'failure_stage' => $stage,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'retry_count' => (int) $failure->retry_count + 1,
                'last_retry_at' => now(),
            ]);

            return;
        }

        IntegrationFailure::create([
            'integration_event_id' => $event->id,
            'failure_stage' => $stage,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'retry_count' => 0,
            'last_retry_at' => now(),
        ]);
    }

    public function resolveOpenFailures(IntegrationEvent $event): void
    {
        IntegrationFailure::query()
            ->where('integration_event_id', $event->id)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
            ]);
    }
}
